<?php

class shopMlmCustomersModel extends waNestedSetModel
{
    protected $table = 'shop_mlm_customers';
    //protected $id = 'code';

    protected $left = 'left_key';
    protected $right = 'right_key';
    protected $parent = 'parent_id';

    const TYPE_STATIC = 0;
    const TYPE_DYNAMIC = 1;

    /**
     * @var shopMlmPlugin $plugin
     */
    private static $plugin;

    private static function getPlugin()
    {
        if (!empty(self::$plugin)) {
            $plugin = self::$plugin;
        } else {
            $plugin = wa()->getPlugin('mlm');
        }
        return $plugin;
    }

    /**
     * @param $contact_id
     * @return mixed
     */
    public function getByContactId($contact_id)
    {
        return $this->getByField('contact_id', $contact_id);
    }

    /**
     * @param $contact_id
     * @return mixed
     */
    public function getByCode($code)
    {
        return $this->getByField('code', $code);
    }

    /**
     * @param $contact_id
     * @return int
     */
    public function add($contact_id, $parent_code=0, $before_id = null)
    {
        do {
            $code = rand(100, 1000000);
        } while ($this->getByField('code', $code));

        if ($parent_code > 0 && $parent = $this->getByField('code', $parent_code)) {
            $parent_contact_id = $parent['contact_id'];
            $parent_id = $parent['id'];
        }
        else {
            $plugin = self::getPlugin();
            $settings = $plugin->getSettings();
            if (isset ($settings['owners'])) {
                $owners = $settings['owners'];
            }

            if (empty($owners)) {
                $admin_id = $this->query('SELECT id FROM wa_contact WHERE create_method = "install" LIMIT 1')->fetchField();
                if (!isset($admin_id) && $admin_id < 1) {
                    $admin_id = $this->query('SELECT id FROM wa_contact LIMIT 1')->fetchField();
                }
                $owners = $settings['owners'] = array($admin_id => array('mlmweight' => 100, 'id' => $admin_id));
            }

            if (!isset($settings['probability'])) {
                $settings['probability'] = 0;
            }

            if (!isset($settings['last_owner_id'])) {
                $owner = reset($owners);
                $settings['last_owner_id'] = $owner['id'];
            }

            if ($settings['probability'] == 0) {
                $parent_contact_id = $this->getNextOwnerId($owners, $settings['last_owner_id']);
                $settings['last_owner_id'] = $parent_contact_id;
            }
            else {
                $parent_contact_id = $this->getRandomOwnerId($owners);
            }

            $plugin->saveSettings($settings);

            $parent = $this->getByField('contact_id', $parent_contact_id);
            if (empty($parent)) {
                $parent_id = 0;
            }
            else {
                $parent_id = $parent['id'];
            }
        }

        if ($this->isOwner($contact_id)) {
            $parent_contact_id = 0;
            $parent_id = 0;
        }

        $data = array(
            'code' => $code,
            'contact_id' => intval($contact_id),
            'parent_contact_id' => intval($parent_contact_id),
            'create_datetime' => date('Y-m-d H:i:s')
        );

        $id = parent::add($data, $parent_id);
        $row = $this->getById($id);

//        exit;
        return $row['code'];
    }

    public function getParent($customer) {
        $customer = $this->getByContactId($customer['contact_id']);
        if (!empty($customer)) {
            $contact = new waContact($customer['parent_contact_id']);
            return $contact->load();
        }
        else false;
    }

    public function getContact($customer) {
        $contact = new waContact($customer['contact_id']);
        return $contact->load();
    }

    private function getNextOwnerId($owners, $last_owner_id) {

        $temp_owners = $owners;
        $owner = reset($temp_owners);
        while (!empty($owner) && $owner['id'] != $last_owner_id) {
            $owner = next($temp_owners);
        }

        $owner = next($temp_owners);
        if (empty($owner)) {
            $owner = reset($temp_owners);
        }
        return $owner['id'];
    }

    private function getRandomOwnerId($owners) {
        $summ = 0;
        foreach ($owners as $owner) {
            $summ += intval($owner['mlmweight']);
        }

        if ($summ != 100) {
            foreach ($owners as &$owner) {
                $owner['mlmweight'] = intval($owner['mlmweight']) * (100 / $summ);
            }
        }

        $max = 0;
        foreach ($owners as &$owner) {
            $min = $max;
            $owner['min'] = $min;
            $max = $owner['max'] = $min + $owner['mlmweight'];
        }

        $owner = array_pop($owners);
        $owner['max'] = 100;

        array_push($owners, $owner);

        $probability = rand(1, 100);

        foreach ($owners as $owner) {
            if ($probability > $owner['min'] && $probability < $owner['max']) {
                $owner_id = $owner['id'];

            }
        }
        return $owner_id;

    }

    public function isOwner($contact_id) {
        $plugin = self::getPlugin();
        $settings = $plugin->getSettings();
        if (isset ($settings['owners'])) {
            $owners = $settings['owners'];
        }
        else false;

        if (isset($owners[$contact_id])) {
            return true;
        }
        else false;
    }

    public function delete($id)
    {
        $id = (int)$id;
        $item = $this->getById($id);
        if (!$item) {
            return false;
        }
        $parent_id = (int)$item['parent_id'];

          // because all descendants will be thrown one level up
        // it's necessary to ensure uniqueness urls of descendants in new environment (new parent)
        foreach (
            $this->descendants($item, false)->order("`{$this->depth}`, `{$this->left}`")->query()
            as $child)
        {

        }

        if (!parent::delete($id)) {
            return false;
        }


        return true;

    }

    /**
     *
     * Query for getting descendants
     *
     * @param mixed $parent
     *     int parent ID
     *     array parent info
     *     false|null query for all tree
     * @param boolean $include_parent
     * @return waDbQuery
     */
    public function descendants($parent, $include_parent = false)
    {
        $query = new waDbQuery($this);

        if (is_numeric($parent) && $parent) {
            $parent_id = (int)$parent;
            $parent = $this->getById($parent);
            if (!$parent) {
                return $query->where('id = '.$parent_id);
            }
        }
        $op = !$include_parent ? array('>', '<') : array('>=', '<=');
        if ($parent) {
            $where = "
                `{$this->left}`  {$op[0]} {$parent[$this->left]} AND
                `{$this->right}` {$op[1]} {$parent[$this->right]}
            ";
            $query->where($where);
        }
        return $query;
    }

    public function recount($category_id = null)
    {
        $cond = "
            WHERE c.type = ".self::TYPE_STATIC."
            GROUP BY c.id
            HAVING c.count != cnt
        ";
        if ($category_id !== null) {
            $category_ids = array();
            foreach ((array)$category_id as $id) {
                $category_ids[] = $id;
            }
            if (!$category_ids) {
                return;
            }
            $cond = "
                WHERE c.id IN ('".implode("','", $this->escape($category_ids))."') AND c.type = ".self::TYPE_STATIC."
                GROUP BY c.id
            ";
        }
        $sql = "
            UPDATE `{$this->table}` c JOIN (
            SELECT c.id, c.count, count(cp.product_id) cnt
            FROM `{$this->table}` c
            LEFT JOIN `shop_category_products` cp ON cp.category_id = c.id
            $cond
            ) r ON c.id = r.id
            SET c.count = r.cnt
        ";
        return $this->exec($sql);
    }

    protected function repairSubtree(&$subtree, $depth = -1, $key = 0)
    {
        $subtree[$this->left] = $key;
        $subtree[$this->depth] = $depth;
        if (!empty($subtree['children'])) {
            foreach ($subtree['children'] as & $node) {
                $key = $this->repairSubtree($node, $depth + 1, $key + 1);
            }
        }
        $subtree[$this->right] = $key + 1;
        return $key + 1;
    }

    /**
     * @param string $fields
     * @param bool $static_only
     * @return array
     */
    public function getFullTree($fields = '', $static_only = false)
    {
        if (!$fields) {
            $fields = 'id, left_key, right_key, parent_id, depth, name, count, type, status';
        }

        $fields = $this->escape($fields);

        $where = $static_only ? 'WHERE type='.self::TYPE_STATIC : '';
        $sql = "SELECT $fields FROM {$this->table} $where ORDER BY {$this->left}";
        return $this->query($sql)->fetchAll('id');
    }

    /**
     * Returns subtree
     *
     * @param int $id
     * @param int $depth related depth default is unlimited
     * @param bool $escape
     * @param array $where
     * @return array
     */
    public function getTree($id, $depth = null, $escape = false)
    {
        $where = array();
        if ($id) {
            $parent = $this->getById($id);
            $left  = (int) $parent[$this->left];
            $right = (int) $parent[$this->right];
        } else {
            $left = $right = 0;
        }


        $sql = "SELECT c.* FROM {$this->table} c";
        if ($id) {
            $where[] = "c.`{$this->left}` >= i:left";
            $where[] = "c.`{$this->right}` <= i:right";
        }
        if ($depth !== null) {
            $depth = max(0, intval($depth));
            if ($id && $parent) {
                $depth += (int)$parent[$this->depth];
            }
            $where[] = "c.`{$this->depth}` <= i:depth";
        }

        if ($where) {
            $sql .= " WHERE (" . implode(') AND (', $where) . ')';
        }
        $sql .= " ORDER BY c.`{$this->left}`";

        $data = $this->query($sql, array('left' => $left, 'right' => $right, 'depth' => $depth))->fetchAll($this->id);

        if ($escape) {
            foreach ($data as &$item) {
                $item['name'] = htmlspecialchars($item['name']);
            }
            unset($item);
        }
        return $data;
    }

    /**
     * Возвращает 3 уровня родителей
     * Уровень 1 — прямой родитель
     * Уровень 2 — родитель родителя (дедушка :) )
     * Уровень 3 — родитель родителя родителя (прадедушка-бугор :) )
     *
     * Если родителя нет, то этого элемента просто не будет
     * [
     *   [1] => [родитель]
     *   [2] => [родитель родителя]
     *   [3] => [родитель родителя родителя]
     * ]
     *
     * @todo Можно модифицировать на любой уровень вложенности
     *
     * @param int $contact_id
     * @return array
     */
    public function getThreeParents($contact_id) {
        $customer = $this->getByContactId($contact_id);
        $result = array();
        for($level=1; $level <= 3 && !empty($customer) && $customer["parent_id"]; $level++) {
            $customer = $this->getById($customer["parent_id"]);
            $result[$level] = $customer;
        }

        return $result;
    }

    /**
     * Добавляет бонусы "родителям" контакта. До трех уровней.
     * Уровень 1 — прямой родитель
     * Уровень 2 — родитель родителя (дедушка :) )
     * Уровень 3 — родитель родителя родителя (прадедушка-бугор :) )
     *
     * @todo Если сумму бонусов считаем в модели shopCustomerModel, то этот метод удалить
     *
     * @see shopMlmPlugin::calculateBonus() Структура массива с бонусами
     *
     * @param int $contact_id
     * @param array $bonus
     */
    public function addBonusToParents($contact_id, $bonus)
    {
        $customer = $this->getByContactId($contact_id);

        for($level=1; $level <= 3 && !empty($customer) && $customer["parent_id"]; $level++) {
            $customer = $this->getById($customer["parent_id"]);
            $this->addBonus($customer["id"], $bonus[$level]["bonus"]);
        }
    }

    /**
     * Подсчитывает количество реферралов для указанного контакта на заданном
     * уровне. Уровень в принципе может быть любым, но сейчас мы используем
     * от 1 до 3
     *
     * @param int|array $customer ID записи из этой модели (int) или массив с данными о контакте из этой модели (array)
     * @param int $level Уровень
     * @return int количество реферралов
     */
    public function countReferrals($customer, $level)
    {
        if(!is_array($customer)) {
            $customer = $this->getById($customer);
        }

        if(empty($customer)) {
            return 0;
        }

        $result = $this->select("COUNT(*) as cnt")->
                where("left_key > i:lft AND right_key < i:rght AND depth=i:depth", array(
                    'lft' => $customer['left_key'],
                    'rght' => $customer['right_key'],
                    'depth' => $customer['depth']+$level
                ))->
                fetchField();

        return $result;
    }

    /**
     * Считает сумму по выполненным заказам для реферралов $level уровня.
     *
     * @todo Необходимо вести собственный учет сумм, статус заказов может быть изменен на отмену и прочее, а начисления уже не изменятся
     *
     * @deprecated since version 0.0.1
     *
     * @param int|array $customer ID записи из этой модели (int) или массив с данными о контакте из этой модели (array)
     * @param int $level Уровень
     * @return float Сумма по выполненным заказам
     */
    public function countReferralPurchasesTotals($customer, $level)
    {
        if(!is_array($customer)) {
            $customer = $this->getById($customer);
        }

        if(empty($customer)) {
            return 0;
        }

        $result = $this->query("SELECT SUM(so.total-so.tax-so.shipping) as `purchases` "
                . "FROM `{$this->table}` `smc` "
                . "LEFT JOIN `shop_order` `so` ON `smc`.`contact_id`=`so`.`contact_id` "
                . "WHERE smc.left_key > i:lft "
                . "AND smc.right_key < i:rght "
                . "AND smc.depth = i:depth "
                . "AND so.state_id='completed'", array(
                    'lft' => $customer['left_key'],
                    'rght' => $customer['right_key'],
                    'depth' => $customer['depth']+$level
                ))->fetchField();

        return $result ? (float)$result : 0;
    }

    /**
     * Добавляет указанный бонус контакту
     *
     * FIXME: обработка ошибок? Выбрасывать какое-то исключение?
     *
     * @todo Если сумму бонусов считаем в модели shopCustomerModel, то этот метод удалить
     *
     * @param int $customer_id
     * @param float $bonus
     */
    private function addBonus($customer_id, $bonus)
    {
        $this->query("UPDATE {$this->table} SET `bonus_total`=`bonus_total`+ f:bonus WHERE id=i:id", array('bonus'=>$bonus, 'id'=>$customer_id));
    }



}
