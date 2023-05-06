<?php
/*
Plugin Name: Woocommerce out-of-stock countdown clock for OrderHive
Plugin URI: https://www.linkedin.com/in/leonardo-r-sampaio
Description: Show a countdown clock for out-of-stock products, using Orderhive Purchase Orders expected delivery dates as reference.
Author: Leonardo Sampaio
Version: 1.07022023
*/

namespace lrs;

require __DIR__.'/vendor/autoload.php';

if (!class_exists('CountdownClock'))
{
    class CountdownClock {

        private static CountdownClock $instance;

        private ProductTab $productTab;

        private function __construct()
        {
            CountdownSettings::getInstance();
            $this->productTab = new ProductTab();

            add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'), 10);
            add_action('woocommerce_before_template_part', array($this, 'showLocationsForOutOfStockProducts'), 10);
        }

        public function showLocationsForOutOfStockProducts()
        {
            if (is_product() &&
                'yes' === (get_post_meta(get_the_ID(), '_manage_stock', true) ?? 'no') &&
                ($product = wc_get_product(get_the_ID())) &&
                !$product->is_in_stock())
            {
                //multi location plugin will show the location selection
                add_filter('woocommerce_product_is_in_stock', '__return_true');

                //wait list plugin should consider the product as out of stock
                add_filter('xoo_wl_product_is_out_of_stock', '__return_true');

                //'out of stock' desktop version sticker
                add_filter(
                    'woocommerce_get_availability',
                    function () {
                        return array(
                            'availability' => 'Out of stock',
                            'class'        => 'out-of-stock',
                        );
                    }
                );
            }
        }

        public static function getInstance()
        {
            if (!isset(self::$instance))
            {
                self::$instance = new static();
            }

            return self::$instance;
        }

        public function enqueueScripts()
        {
            if (!is_product() || 'yes' !== (get_post_meta(get_the_ID(), '_manage_stock', true) ?? 'no'))
            {
                return;
            }

            $filteredExpectedDates  = [];
            $expectedDates          = get_post_meta(get_the_ID(), '_orderhive_warehouses_expected_dates', true) ?? [];
            $locations              = get_terms(array('taxonomy' => 'locations', 'hide_empty' => false, 'parent' => 0)) ?? [];
            $daysToAdd              = (int) (get_option('countdown_clock_days_to_add') ?? 0);

            foreach($locations as $location)
            {
                if (($warehouseId = get_term_meta($location->term_id, 'wcmlim_orderhive_warehouse_id', true)) &&
                    !empty($expectedDateForLocation = $expectedDates[$warehouseId]))
                {
                    try
                    {
                        $date = new \DateTime($expectedDateForLocation);
                        if (0 !== $daysToAdd)
                        {
                            $date->add(new \DateInterval('P'.$daysToAdd.'D'));
                        }
                        $filteredExpectedDates[$location->slug] = $date->format('Y-m-d');
                    }
                    catch (\Exception $e)
                    {
                        error_log(
                            'CountdownClock: '.$e->getMessage().
                            ' ('.$e->getFile().':'.$e->getLine().')'
                        );
                    }
                }
            }

            wp_register_script('countdown_clock',
                plugin_dir_url( __FILE__ ) . '/frontend/public/js/clock.js', 
                array('wcmlim'),
                '1.0' ,
                true);
            wp_enqueue_script('countdown_clock');

            wp_localize_script(
                'countdown_clock',
                'config',
                array(
                    'expected_dates'    => $filteredExpectedDates,
                    'clock_html'        => file_get_contents(plugin_dir_path( __FILE__ ) . 
                                            '/frontend/public/clock.html')
                )
            );

            wp_enqueue_style('countdown_clock',
                plugin_dir_url( __FILE__ ) . '/frontend/public/css/clock.css',
                array('wcmlim'),
                '1.0'
            );
        }
    }
}

$countdownClock = CountdownClock::getInstance();