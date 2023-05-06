<?php

namespace lrs;

if (!class_exists('ProductTab'))
{
    class ProductTab {

        private OrderhiveAPIWrapper $api;

        public function __construct()
        {
            $this->api = new OrderhiveAPIWrapper([
                'refresh_token_retry_number' => 0 //to avoid locking the frontend
            ]);

            add_filter('woocommerce_product_data_tabs', array($this, 'expectedDatesProductTab'));
            add_action('woocommerce_product_data_panels', array($this, 'expectedDatesProductDataPanel'));
        }

        public function expectedDatesProductTab(array $tabs): array
        {
            $tabs = array_merge( $tabs, array(
                'expected_dates' => array(
                    'label'  => 'OH Expected Dates',
                    'target' => 'expected_dates_data',
                    'class'  => array(),
                ),
            ) );
        
            return $tabs;
        }

        public function expectedDatesProductDataPanel(): void { ?>
            <div id="expected_dates_data" class="panel woocommerce_options_panel hidden">
        
                    <?php

                    //test
                    // update_post_meta(get_the_ID(), '_orderhive_warehouses_expected_dates', [
                    //     47210 => '2022-08-30',
                    // ]);
                    $this->api->syncPurshaseOrders();
                    $expectedDates =
                        get_post_meta(get_the_ID(), '_orderhive_warehouses_expected_dates', true) ?? [];

                    ?>
                    <table class="expected_dates_table">
                        <tr>
                            <th>Orderhive Warehouse</th>
                            <th>Expected Date</td>
                        </tr>
                        <?php

                        if (!empty($expectedDates))
                        {
                            $apiWarehouses = [];
                            try
                            {
                                $apiWarehouses = $this->api->getWarehouses();
                            }
                            catch (\Exception $e){
                                error_log($e->getMessage());
                            }

                            $warehouses = [];
                            foreach($apiWarehouses as $apiWarehouse)
                            {
                                $warehouses[$apiWarehouse['id']] = $apiWarehouse['name'];
                            }

                            foreach($expectedDates as $warehouseId => $date)
                            {
                                $date = (new \DateTime($date))->format('d/m/Y');
                                echo "<tr><td>".
                                    (isset($warehouses[$warehouseId]) ? "{$warehouses[$warehouseId]} ({$warehouseId})" : $warehouseId).
                                    "</td><td>{$date}</td></tr>";
                            }
                        }
                        ?>
                    </table>

                    <p><span>Last update: <?php 
                        echo !empty($meta = get_post_meta(get_the_ID(), '_orderhive_warehouses_expected_dates_last_update', true)) ?
                            (new \DateTime($meta))->format('d/m/Y H:i:s') : 'never'; ?></span></p>
            </div>
        
        <?php 

        wp_enqueue_style(
            'countdown_clock_admin_css',
            plugin_dir_url( __FILE__ ) . '../frontend/admin/css/tab.css',
            array('wcmlim'),
            '1.0'
        );    
    }
    }
}