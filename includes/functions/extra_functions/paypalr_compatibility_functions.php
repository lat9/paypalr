<?php
/**
 * Part of the paypalr (PayPal Restful Api) payment module.
 *
 * These functions may not exist on older versions of Zen Cart. 
 * They are included here for backward-compatibility.
 *
 * Last updated: v1.3.0
 */

if (!function_exists('zen_get_buyable_product_type_handlers')) {
    /**
     * Get a list of product page names that identify buyable products.
     * This allows us to mark a page as containing a product which can
     * be allowed to add-to-cart or buy-now with various modules.
     */
    function zen_get_buyable_product_type_handlers(): array
    {
        global $db;
        $sql = "SELECT type_handler from " . TABLE_PRODUCT_TYPES . " WHERE allow_add_to_cart = 'Y'";
        $results = $db->Execute($sql);
        $retVal = [];
        foreach ($results as $result) {
            $retVal[] = $result['type_handler'] . '_info';
        }
        return $retVal;
    }
}
