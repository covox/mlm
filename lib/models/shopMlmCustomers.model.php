<?php

class shopMlmCustomersModel extends shopCustomerModel
{
    protected $table = 'shop_mlm_customers';
    protected $id = 'code';

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
     * @return int
     */
    public function add($contact_id, $parent_code=0)
    {
        do {
            $code = rand(100, 1000000);
        } while ($this->getById($code));

        if ($parent_code > 0 && $parent = $this->getById($parent_code)) {
            $parent_id = $parent['contact_id'];
        }
        else {
            /**
             * @var shopMlmPlugin $plugin
             */
            $plugin = wa('shop')->getPlugin('mlm');
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
                $parent_id = $this->getNextOwnerId($owners, $settings['last_owner_id']);
                $settings['last_owner_id'] = $parent_id;
            }
            else {
                $parent_id = $this->getRandomOwnerId($owners);
            }

            $plugin->saveSettings($settings);
        }

        $this->insert(array(
            'code' => $code,
            'contact_id' => $contact_id,
            'parent_id' => $parent_id,
            'create_datetime' => date('Y-m-d H:i:s')
        ));
        return $code;
    }

    public function getParent($customer) {
        $contact = new waContact($customer['parent_id']);
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

    public function getReferralsList($start=0, $limit=50, $order='name')
    {
        $start = (int) $start;
        $limit = (int) $limit;

        $join = array();
        $select = array(
            'sc.*, c.*, o.create_datetime AS last_order_datetime'
        );

        $join[] = 'JOIN shop_referrals AS r ON r.contact_id=c.id';

        if ($join) {
            $join = implode("\n", $join);
        } else {
            $join = '';
        }

        $possible_orders = array(
            'name' => 'c.name',
            '!name' => 'c.name DESC',
            'total_spent' => 'sc.total_spent',
            '!total_spent' => 'sc.total_spent DESC',
            'affiliate_bonus' => 'sc.affiliate_bonus',
            '!affiliate_bonus' => 'sc.affiliate_bonus DESC',
            'number_of_orders' => 'sc.number_of_orders',
            '!number_of_orders' => 'sc.number_of_orders DESC',
            'last_order' => 'sc.last_order_id',
            '!last_order' => 'sc.last_order_id DESC',
            'registered' => 'c.create_datetime',
            '!registered' => 'c.create_datetime DESC',
        );

        if (!$order || empty($possible_orders[$order])) {
            $order = key($possible_orders);
        }
        $order = 'ORDER BY '.$possible_orders[$order];

        // Fetch basic contact and customer info
        $sql = "SELECT SQL_CALC_FOUND_ROWS ".implode(', ', $select)."
                FROM wa_contact AS c
                    JOIN shop_customer AS sc
                        ON c.id=sc.contact_id
                    LEFT JOIN shop_order AS o
                        ON o.id=sc.last_order_id
                    $join
                GROUP BY c.id
                $order
                LIMIT {$start}, {$limit}";

        $customers = $this->query($sql)->fetchAll('id');

        $total = $this->query('SELECT FOUND_ROWS()')->fetchField();

        // get emails
        $ids = array_keys($customers);
        if ($ids) {
            foreach ($this->query("
                SELECT contact_id, email, MIN(sort)
                FROM `wa_contact_emails`
                WHERE contact_id IN (".implode(',', $ids).")
                GROUP BY contact_id") as $item)
            {
                $customers[$item['contact_id']]['email'] = $item['email'];
            }
        }

        if (!$customers) {
            return array(array(), 0);
        }

        // Fetch addresses
        foreach($customers as &$c) {
            $c['address'] = array();
        }
        unset($c);

        $sql = "SELECT *
                FROM wa_contact_data
                WHERE contact_id IN (i:ids)
                    AND sort=0
                    AND field LIKE 'address:%'
                ORDER BY contact_id";
        foreach ($this->query($sql, array('ids' => array_keys($customers))) as $row) {
            $customers[$row['contact_id']]['address'][substr($row['field'], 8)] = $row['value'];
        }

        return array($customers, $total);
    }
}
