<?php

namespace lrs;

if (!class_exists('CountdownSettings')) {
    class CountdownSettings
    {
        private string $cronHook = 'countdown_clock_cron_job';

        private static CountdownSettings $instance;

        private string $token;
        private string $refreshToken;
        private string $aws4Path;
        private string $daysToAdd;
        private string $schedulingOption;

        private OrderhiveAPIWrapper $api;

        private function __construct()
        {
            //multi-locations-orderhive-api-integration plugin
            $this->token =              get_option('wcmlim_orderhive_proxy_url');
            $this->refreshToken =       get_option('wcmlim_orderhive_proxy_key');
            $this->aws4Path =           get_option('wcmlim_orderhive_proxy_port');

            //local values
            $this->daysToAdd =          (int)(get_option('countdown_clock_days_to_add'));
            $this->schedulingOption =   (int)(!empty(get_option('countdown_clock_scheduling_option')) ?
                                            get_option('countdown_clock_scheduling_option') : 12);

            $this->api = new OrderhiveAPIWrapper();

            $this->drawMenus();

            add_action($this->cronHook, array($this->api, 'syncPurshaseOrders'));
            $this->registerCronActionAndHooks();
        }

        public static function getInstance(): CountdownSettings
        {
            if (!isset(self::$instance)) {
                self::$instance = new static();
            }

            return self::$instance;
        }

        public function enableCron() : bool
        {
            if (!wp_next_scheduled($this->cronHook)) {
                return wp_schedule_event(time(), 'coundownclock', $this->cronHook);
            }
            return false;
        }

        public static function disableCron() : bool
        {
            return wp_clear_scheduled_hook(CountdownSettings::getInstance()->getCronHook()) > 0;
        }

        public function addCronInterval( array $schedules ) : array
        {
            $schedules['coundownclock'] = array(
                    'interval'  => 60 * 60 * $this->schedulingOption,
                    'display'   => "Orderhive API purchase orders sync"
            );
            return $schedules;
        }

        public function validateUpdatedOptions($optionName): bool
        {
            if ('countdown_clock_scheduling_option' === $optionName) {
                return $this->disableCron() && $this->enableCron();
            }
            return true;
        }

        private function registerCronActionAndHooks(): bool
        {
            try {
                add_filter('cron_schedules', array($this, 'addCronInterval'));
    
                register_activation_hook(__FILE__, array($this, 'enableCron'));
                add_action('init', array($this, 'enableCron'));
    
                register_uninstall_hook(__FILE__, 'CountdownSettings::disableCron');
                register_deactivation_hook(__FILE__, array($this, 'disableCron'));
    
                add_action('updated_option', array($this, 'validateUpdatedOptions'), 10, 1);

                return true;
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }

            return false;
        }

        public function getCronHook() : string
        {
            return $this->cronHook;
        }

        private function drawMenus(): void
        {
            add_action('admin_menu', function() {
                add_options_page(
                    'Countdown Clock Settings',
                    'Countdown Clock',
                    'manage_options',
                    'countdown-clock-settings',
                    function () {
                        echo '<div class="wrap">';
                        echo '<h1>Countdown Clock Settings</h1>';
                        echo '<form method="post" action="options.php">';
                        settings_fields('countdown_clock_settings');
                        do_settings_sections('countdown-clock-settings');
                        submit_button();
                        echo '</form>';
                        echo '</div>';
                    }
                );
            });

            add_action('admin_init', function () {
                register_setting('countdown_clock_settings', 'wcmlim_orderhive_proxy_url');
                register_setting('countdown_clock_settings', 'wcmlim_orderhive_proxy_key');
                register_setting('countdown_clock_settings', 'wcmlim_orderhive_proxy_port');
                register_setting('countdown_clock_settings', 'countdown_clock_days_to_add');
                register_setting('countdown_clock_settings', 'countdown_clock_scheduling_option');

                add_settings_section(
                    'countdown_clock_settings_oh_section',
                    '',
                    function () {
                        echo '<p>';
                        echo '<h2>Orderhive API</h2>';
                        echo '</p>';
                    },
                    'countdown-clock-settings'
                );

                add_settings_field(
                    'countdown_clock_orderhive_token',
                    'Proxy URL',
                    function () {
                        echo '<input type="text" name="wcmlim_orderhive_proxy_url" value="'.$this->token.'" />';
                    },
                    'countdown-clock-settings',
                    'countdown_clock_settings_oh_section'
                );

                add_settings_field(
                    'countdown_clock_orderhive_refresh_token',
                    'Proxy key',
                    function () {
                        echo '<input type="text" name="wcmlim_orderhive_proxy_key" value="'.$this->refreshToken.'" />';
                    },
                    'countdown-clock-settings',
                    'countdown_clock_settings_oh_section'
                );

                add_settings_field(
                    'countdown_clock_orderhive_aws4_token_path',
                    'Proxy port',
                    function () {
                        echo '<input type="text" name="wcmlim_orderhive_proxy_port" value="'.$this->aws4Path.'" />';
                    },
                    'countdown-clock-settings',
                    'countdown_clock_settings_oh_section'
                );

                add_settings_section(
                    'countdown_clock_settings_clock_section',
                    '',
                    function () {
                        echo '<p>';
                        echo '<h2>Clock options</h2>';
                        echo '</p>';
                    },
                    'countdown-clock-settings'
                );

                add_settings_field(
                    'countdown_clock_days_to_add',
                    'Days to add to expected date',
                    function () {
                        echo '<input type="text" name="countdown_clock_days_to_add" value="'.$this->daysToAdd.'" />';
                    },
                    'countdown-clock-settings',
                    'countdown_clock_settings_clock_section'
                );

                add_settings_field(
                    'countdown_clock_scheduling_option',
                    'Verify Orderhive Purchase Orders every',
                    function () {
                        echo '<input type="text" name="countdown_clock_scheduling_option" value="' .
                            $this->schedulingOption.'" /> hour(s)';
                    },
                    'countdown-clock-settings',
                    'countdown_clock_settings_clock_section'
                );
            });
        }
    }
}