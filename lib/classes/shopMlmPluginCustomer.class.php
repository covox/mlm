<?php
/**
 * Created by PhpStorm.
 * User: snark | itfrogs.ru
 * Date: 05.11.14
 * Time: 21:48
  */

class shopMlmPluginCustomer {

    /**
     * @var waContactSettingsModel
     */
    private static $settings_model;

    private $list;
    private $root_id;

    public function __construct($root_id = 0)
    {
        $contact_id = wa()->getUser()->getId();
        $mlmCustomersModel = new shopMlmCustomersModel();
        $customer = $mlmCustomersModel->getByContactId($contact_id);
        if (!empty($customer)) {
            $this->root_id = $customer['id'];
        }
        else $this->root_id = 0;
    }

    /**
     * @return waContactSettingsModel
     */
    private static function getSettingsModel()
    {
        if (!self::$settings_model) {
            self::$settings_model = new waContactSettingsModel();
        }
        return self::$settings_model;
    }

    public function getList()
    {
        if ($this->list === null) {
            $this->list = $this->_getList($this->root_id, $depth = null);
        }
        return $this->list;
    }

    public  function getTree($parent_id = 0, $depth = null)
    {
        $mlmCustomersModel = new shopMlmCustomersModel();
        if ($parent_id) {
            $categories = $mlmCustomersModel->getTree($parent_id, $depth);
        } else {
            $categories = $mlmCustomersModel->getFullTree('id, left_key, right_key, parent_id, depth, contact_id, parent_contact_id');
        }

        // children_count is number of children of category
        foreach ($categories as &$item) {
            if (!isset($item['children_count'])) {
                $item['children_count'] = 0;
            }
            if (isset($categories[$item['parent_id']])) {
                $parent = &$categories[$item['parent_id']];
                if (!isset($parent['children_count'])) {
                    $parent['children_count'] = 0;
                }
                ++$parent['children_count'];
                unset($parent);
            }
        }
        unset($item);

        // bind storefronts (routes)
        $category_routes_model = new shopCategoryRoutesModel();
        foreach ($category_routes_model->getRoutes(array_keys($categories)) as $category_id => $routes) {
            foreach ($routes as &$r) {
                if (substr($r, -1) === '*') {
                    $r = substr($r, 0, -1);
                }
                if (substr($r, -1) === '/') {
                    $r = substr($r, 0, -1);
                }
            }
            unset($r);
            $categories[$category_id]['routes'] = $routes;
        }

        // form intermediate utility data structure
        $stack = array();
        $hierarchy = array();
        foreach ($categories as $item) {
            $c = array(
                'id' => $item['id'],
                'total_count' => 0,
                'parent_id' => $item['parent_id'],
                //'count' => $item['count'],
                'depth' => $item['depth'],
                'children' => array()
            );

            // Number of stack items
            $l = count($stack);

            // Check if we're dealing with different levels
            while($l > 0 && $stack[$l - 1]['depth'] >= $item['depth']) {
                array_pop($stack);
                $l--;
            }

            // Stack is empty (we are inspecting the root)
            if ($l == 0) {
                // Assigning the root node
                $i = count($hierarchy);
                $hierarchy[$i] = $c;
                $stack[] = & $hierarchy[$i];
            } else {
                // Add node to parent
                $i = count($stack[$l - 1]['children']);
                $stack[$l - 1]['children'][$i] = $c;
                $stack[] = & $stack[$l - 1]['children'][$i];
            }
        }

        $hierarchy = array(
            'id' => 0,
            'count' => 0,
            'total_count' => 0,
            'children' => $hierarchy
        );
        $this->totalCount($hierarchy, $categories);

        return $categories;
    }

    private function totalCount(&$tree, &$plain_list)
    {
        //$total = $tree['count'];
        $total = 0;
        foreach ($tree['children'] as &$node) {
            $total += $this->totalCount($node, $plain_list);
        }
        if (isset($plain_list[$tree['id']])) {
            $plain_list[$tree['id']]['total_count'] = $total;
        }
        return $total ;
    }

    /**
     * Get categories by parent ID (if 0 that all categories).
     * Take into account collapsed/expanded states
     * @param int $parent_id
     * @return array
     */
    private function _getList($parent_id = 0, $depth = null)
    {
        // map indexed by category ids indicates that this category need or not
        // to unset for output flow
        $expand = array();
        $unset = array();

        $categories = self::getTree($parent_id, $depth);

        $root = null;
        if ($parent_id) {
            $root = $categories[$parent_id];
        }

        foreach ($categories as $category) {
            if (empty($expand[$category['parent_id']])
                || !empty($unset[$category['parent_id']]))
            {
                $unset[$category['id']] = true;
            }
        }

        foreach ($categories as $category_id => &$category) {
            $category['expanded'] = !empty($expand[$category_id]);
            if (!empty($unset[$category_id])) {
                unset($categories[$category_id]);
            }
        }
        unset($category);

        if ($root) {

            $depth = $root['depth'];

            // root is not needed
            if (isset($categories[$parent_id])) {
                unset($categories[$parent_id]);
            }

            // shift depth for correct rendering
            foreach ($categories as &$category) {
                $category['depth'] -= $depth + 1;
            }
            unset($category);
        }

        return $categories;
    }

    public function customers($id = 0, $depth = null)
    {
        if ($id === true) {
            $id = 0;
            $tree = true;
        }
        $mlmCustomersModel = new shopMlmCustomersModel();

        $customers = $mlmCustomersModel->getTree($id, $depth);

        $hidden = array();
        foreach ($customers as $c_id => $c) {
            $contact = $mlmCustomersModel->getContact($c);
            if ($c['parent_id'] && $c['id'] != $id && !isset($customers[$c['parent_id']])) {
                unset($customers[$c_id]);
            } else {
                $customers[$c_id]['name'] = htmlspecialchars($contact['name']);
            }
        }

        if ($id && isset($customers[$id])) {
            unset($customers[$id]);
        }


        $stack = array();
        $result = array();
        foreach ($customers as $c) {
            $c['childs'] = array();

            // Number of stack items
            $l = count($stack);

            // Check if we're dealing with different levels
            while($l > 0 && $stack[$l - 1]['depth'] >= $c['depth']) {
                array_pop($stack);
                $l--;
            }

            // Stack is empty (we are inspecting the root)
            if ($l == 0) {
                // Assigning the root node
                $i = count($result);
                $result[$i] = $c;
                $stack[] = & $result[$i];
            } else {
                // Add node to parent
                $i = count($stack[$l - 1]['childs']);
                $stack[$l - 1]['childs'][$i] = $c;
                $stack[] = & $stack[$l - 1]['childs'][$i];
            }
        }
        return $result;
    }

}
