<?php

namespace lrs;

if (!class_exists('OrderhiveAPIWrapper'))
{
    class OrderhiveAPIWrapper {

        private array $credentials;

        public function __construct(array $optionalArgs = [])
        {
            $this->credentials = $this->getCredentials($optionalArgs);
        }

        private function post($url, $post = [], $port = 443): array
        {
            $consumer = curl_init();
        
            if (!empty($post))
            {
                curl_setopt($consumer, CURLOPT_POSTFIELDS, http_build_query($post));
            }
        
            curl_setopt($consumer, CURLOPT_URL, $url);
            curl_setopt($consumer, CURLOPT_PORT, $port);
            
            curl_setopt($consumer, CURLOPT_HEADER, 0);
            curl_setopt($consumer, CURLOPT_POST, 1); 
            curl_setopt($consumer, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($consumer, CURLOPT_SSL_VERIFYPEER, 1);
        
            $response = curl_exec($consumer);
            $httpcode = curl_getinfo($consumer, CURLINFO_HTTP_CODE);
        
            if (curl_errno($consumer))
            { 
                $response = curl_error($consumer);
                curl_close($consumer); 
            }
        
            return [
                'status'=>$httpcode,
                'response'=>$response
            ];
        }

        public function syncPurshaseOrders(): void
        {
            try
            {
                if (empty($orders = $this->getPurshaseOrders()))
                {
                    return;
                }

                $visitedProducts = [];
                
                foreach ($orders as $order)
                {
                    if (empty($expectedDate = $order['expected_date']) || $expectedDate < date('Y-m-d'))
                    {
                        continue;
                    }
    
                    foreach($order['purchaseOrderItems'] as $item)
                    {
                        if (($sku = $item['sku']) && ($productId = wc_get_product_id_by_sku($sku)))
                        {
                            $visitedProducts[$productId] =
                                !isset($visitedProducts[$productId]) ? $expectedDate : min($visitedProducts[$productId], $expectedDate);
                            
                            $meta = !empty($metaValue = get_post_meta($productId, '_orderhive_warehouses_expected_dates', true)) && is_array($metaValue) ?
                                    $metaValue : [];
    
                            $meta[$order['warehouse']['id']] = (new \DateTime($visitedProducts[$productId]))->format('Y-m-d');
    
                            update_post_meta($productId, "_orderhive_warehouses_expected_dates", $meta);
                            update_post_meta($productId, "_orderhive_warehouses_expected_dates_last_update",
                                                (new \DateTime('now'))->format('Y-m-d H:i:s'));
                        }
                    }
                }
            }
            catch (\Exception $e)
            {
                error_log($e->getMessage());
            }
        }

        public function getWarehouses() : array
        {
            try {
                $params = [
                    'method' =>     'GET',
                    'endpoint' =>   '/setup/warehouse',
                    'key' =>        $this->credentials['proxyKey']
                ];
                return json_decode(
                    $this->post($this->credentials['proxyUrl'], $params, $this->credentials['proxyPort'])['response'],
                    true
                )['warehouses'];
            }
            catch (\Exception $e){
                error_log($e->getMessage());
                return [];
            }
        }

        /**
         * Status: DRAFT(1) RAISE(2) PARTIAL RECEIVED(3) FULLY RECEIVED(4) COMPLETE(5)
         * 
         * @return array
         */
        public function getPurshaseOrders() : array
        {
            try
            {
                $params = [
                    'method' =>     'POST',
                    'endpoint' =>   '/purchaseorder/purchase',
                    'key' =>        $this->credentials['proxyKey'],

                    'filters'=> [
                        'purchaseOrderStatuses' => [1,2,3,4]
                    ],
                    'page'=>    [
                        'pageId'    => 0,
                        'limit'     => 1000
                    ]
                ];

                return json_decode(
                    $this->post($this->credentials['proxyUrl'], $params, $this->credentials['proxyPort'])['response'],
                    true
                )['data'] ?? [];
            }
            catch (\Exception $e)
            {
                error_log($e->getMessage());
                return [];
            }
        }

        private function getCredentials(array $optionalArgs = []): array
        {
            if (empty($proxyKey = get_option('wcmlim_orderhive_proxy_key', '')))
            {
                error_log("No Orderhive proxy key value provided");
                return [];
            }
        
            if (empty($proxyUrl = get_option('wcmlim_orderhive_proxy_url', '')))
            {
                error_log("No Orderhive proxy url value provided");
                return [];
            }
        
            $params = [
                'proxyKey' => $proxyKey,
                'proxyUrl' => $proxyUrl,
                'proxyPort' => (int) get_option('wcmlim_orderhive_proxy_port', 99)
            ];

            return array_merge($params, $optionalArgs);
        }
    }
}