<?php

namespace FluentFormPro\Integrations\CleverReach;

use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Foundation\Application;
use FluentForm\Framework\Helpers\ArrayHelper;
use WpFluent\Exception;

class Bootstrap extends IntegrationManager
{
    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            'Clever Reach',
            'cleverreach',
            '_fluentform_cleverreach_settings',
            'cleverreach_feed',
            36
        );

        add_filter('fluentform_notifying_async_cleverreach', '__return_false');

        $this->logo = $this->app->url('public/img/integrations/clever_reach.png');

        $this->description = 'CleverReach is web-based email marketing software for managing email campaigns and contacts. A cloud solution that helps companies around the world create and analyze email marketing campaigns.';

        $this->registerAdminHooks();

        add_action('admin_init', function () {
            if (isset($_REQUEST['ff_cleverreach_auth'])) {
                $client = $this->getRemoteClient();
                if (isset($_REQUEST['code'])) {
                    // Get the access token now
                    $code = sanitize_text_field($_REQUEST['code']);
                    $settings = $this->getGlobalSettings([]);
                    $settings = $client->generateAccessToken($code, $settings);
                    if (!is_wp_error($settings)) {
                        $settings['status'] = true;
                        update_option($this->optionKey, $settings, 'no');
                    }
                    wp_redirect(admin_url('admin.php?page=fluent_forms_settings#general-cleverreach-settings'));
                    exit();
                }
                else {
                    $client->redirectToAuthServer();
                }
                die();
            }
        });
    }

    public function getRemoteClient()
    {
        $settings = $this->getGlobalSettings([]);
        return new API(
            $settings
        );
    }

    public function getGlobalSettings($settings)
    {
        $globalSettings = get_option($this->optionKey);

        if (!$globalSettings) {
            $globalSettings = [];
        }

        $defaults = [
            'client_id' => '',
            'client_secret' => '',
            'status' => false,
            'access_token' => '',
            'refresh_token' => '',
            'expire_at' => false
        ];

        return wp_parse_args($globalSettings, $defaults);
    }

    public function saveGlobalSettings($settings)
    {
        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            $integrationSettings = [
                'client_id' => '',
                'client_secret' => '',
                'status' => false,
                'access_token' => ''
            ];
            // Update the details with siteKey & secretKey.
            update_option($this->optionKey, $integrationSettings, 'no');

            wp_send_json_success([
                'message' => __('Your settings has been updated', 'ffcleverreach'),
                'status' => false
            ], 200);
        }

        // Verify API key now
        try {
            $oldSettings = $this->getGlobalSettings([]);
            $oldSettings['client_id'] = sanitize_text_field($settings['client_id']);
            $oldSettings['client_secret'] = sanitize_text_field($settings['client_secret']);
            $oldSettings['status'] = false;
            update_option($this->optionKey, $oldSettings, 'no');

            $client = $this->getRemoteClient();
            $check = $client->checkForClientId();
            if (is_wp_error($check)) {
                $integrationSettings = [
                    'client_id' => '',
                    'client_secret' => '',
                    'status' => false,
                    'access_token' => ''
                    ];
                    update_option($this->optionKey, $integrationSettings, 'no');

                    wp_send_json_error([
                        'message' => __($check->errors['invalid_client'][0],'ffclevereach'),
                        'status' => false
                    ], 400);
                }
            else {
                wp_send_json_success([
                    'message' => __('You are redirect to authenticate', 'ffclevereach'),
                    'redirect_url' => admin_url('?ff_cleverreach_auth')
                ], 200);
            }

        } catch (\Exception $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage()
            ], 400);
        }

    }

    public function getGlobalFields($fields)
    {
        return [
            'logo' => $this->logo,
            'menu_title' => __('Clever Reach Settings', 'ffcleverreach'),
            'menu_description' => $this->description,
            'valid_message' => __('Your Clever Reach API Key is valid', 'ffcleverreach'),
            'invalid_message' => __('Your Clever Reach API Key is not valid', 'ffcleverreach'),
            'save_button_text' => __('Save Settings', 'ffcleverreach'),
            'config_instruction' => $this->getConfigInstractions(),
            'fields' => [
                'client_id' => [
                    'type' => 'text',
                    'placeholder' => 'Clever Reach Client ID',
                    'label_tips' => __('Enter your Clever Reach Client ID', 'ffcleverreach'),
                    'label' => __('Clever Reach Client ID', 'ffcleverreach'),
                ],
                'client_secret' => [
                    'type' => 'password',
                    'placeholder' => 'Clever Reach App Client Secret',
                    'label_tips' => __('Enter your Clever Reach Client secret', 'ffcleverreach'),
                    'label' => __('Clever Reach Client Secret', 'ffcleverreach'),
                ],
            ],
            'hide_on_valid' => true,
            'discard_settings' => [
                'section_description' => 'Your Clever Reach API integration is up and running',
                'button_text' => 'Disconnect Clever Reach',
                'data' => [
                    'client_id' => '',
                    'client_secret' => '',
                    'access_token' => '',
                ],
                'show_verify' => true
            ]
        ];
    }

    protected function getConfigInstractions()
    {
        ob_start();
        ?>
        <div>
            <h4>To Authenticate Clever Reach you have to enable your API first</h4>
            <ol>
                <li>Go to Your Clever reach account dashboard, Click on the profile icon on the top right
                    corner. Click on My Account >> Extras >> REST Api then click on Create an OAuth App now button.
                </li>
                <li>Then give your oauth app a name >> choose REST API Version 3 >> Select the Forms scope >> Redirect
                    URL should be '*' and save it.<br/>
                </li>
                <li>Paste your clever reach account Client Id and Secret Id. Then click save settings.
                </li>
            </ol>
        </div>
        <?php
        return ob_get_clean();
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title' => $this->title . ' Integration',
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => 'Configration required!',
            'global_configure_url' => admin_url('admin.php?page=fluent_forms_settings#general-cleverreach-settings'),
            'configure_message' => 'Clever Reach is not configured yet! Please configure your Clever Reach api first',
            'configure_button_text' => 'Set Clever Reach API'
        ];
        return $integrations;
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        return [
            'name' => '',
            'list_id' => '',
            'email' => '',
            'firstname' => '',
            'lastname' => '',
            'website' => '',
            'company' => '',
            'phone' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'fields' => (object)[],
            'other_fields_mapping' => [
                [
                    'item_value' => '',
                    'label' => ''
                ]
            ],
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
            'resubscribe' => false,
            'enabled' => true
        ];
    }

    public function getSettingsFields($settings, $formId)
    {
        return [
            'fields' => [
                [
                    'key' => 'name',
                    'label' => 'Feed Name',
                    'required' => true,
                    'placeholder' => 'Your Feed Name',
                    'component' => 'text'
                ],
                [
                    'key' => 'list_id',
                    'label' => 'Clever Reach List',
                    'placeholder' => 'Select clever reach List',
                    'tips' => 'Select the Clever Reach list you would like to add your contacts to.',
                    'component' => 'list_ajax_options',
                    'options' => $this->getLists(),
                ],
                [
                    'key' => 'fields',
                    'require_list' => true,
                    'label' => 'Map Fields',
                    'tips' => 'Associate your Clever Reach merge tags to the appropriate Fluent Form fields by selecting the appropriate form field from the list.',
                    'component' => 'map_fields',
                    'field_label_remote' => 'Clever Reach Field',
                    'field_label_local' => 'Form Field',
                    'primary_fileds' => [
                        [
                            'key' => 'email',
                            'label' => 'Email Address',
                            'required' => true,
                            'input_options' => 'emails'
                        ],
                    ]
                ],
                [
                    'key' => 'other_fields_mapping',
                    'require_list' => false,
                    'label' => 'Other Fields',
                    'tips' => 'Select which Fluent Form fields pair with their<br /> respective Clever Reach fields.',
                    'component' => 'dropdown_many_fields',
                    'field_label_remote' => 'Clever Reach Field',
                    'field_label_local' => 'Clever Reach Field',
                    'options' => $this->getOtherFields()
                ],
            ],
            'integration_title' => $this->title
        ];
    }

    protected function getLists()
    {
        $client = $this->getRemoteClient();
        $settings = get_option($this->optionKey);

        try {
            $token = ($settings['access_token']);
            $lists = $client->makeRequest('https://rest.cleverreach.com/groups', null, 'GET',
                ['Authorization' => 'Bearer ' . $token]);
            if (!$lists) {
                return [];
            }
        } catch (\Exception $exception) {
            return [];
        }

        $formattedLists = [];
        foreach ($lists as $list) {
            $formattedLists[$list['id']] = $list['name'];
        }

        return $formattedLists;
    }

    public function getOtherFields()
    {
        // BIND STATIC CAUSE SOME FIELDS ARE NOT SUPPORTED
        $attributes = [
            "firstname" => "First Name",
            "lastname" => "Last Name",
            "company" => "Company",
            "website" => "Website",
            "phone" => "Phone",
            "address" => "Address",
            "city" => "City",
            "state" => "State",
            "zipcode" => "Zipcode",
            "country" => "Country",
        ];

        return $attributes;
    }

    public function getMergeFields($list, $listId, $formId)
    {
        $client = $this->getRemoteClient();

        if (!$this->isConfigured()) {
            return false;
        }

        $settings = get_option($this->optionKey);

        try {
            $token = ($settings['access_token']);
            $lists = $client->makeRequest('https://rest.cleverreach.com/groups/' . $listId . '/attributes/', null,'GET', ['Authorization' => 'Bearer ' . $token]);

            if (!$lists) {
                return false;
            }
        } catch (\Exception $exception) {
            return false;
        }

        $mergedFields = $lists;
        $fields = [];

        foreach ($mergedFields as $merged_field) {
            $fields[$merged_field['name']] = $merged_field['name'];
        }

        return $fields;
    }

    public function notify($feed, $formData, $entry, $form)
    {
        $feedData = $feed['processedValues'];

        if (!is_email($feedData['email'])) {
            $feedData['email'] = ArrayHelper::get($formData, $feedData['email']);
        }

        if (!is_email($feedData['email'])) {
            do_action('ff_integration_action_result', $feed, 'failed', 'Clever Reach API call has been skipped because no valid email available');
            return;
        }

        $subscriber = [];
        $subscriber['list_id'] = $feedData['list_id'];
        $subscriber['email'] = $feedData['email'];
        $subscriber['attributes'] = ArrayHelper::get($feedData, 'fields');
        foreach (ArrayHelper::get($feedData, 'other_fields_mapping') as $item) {
            $subscriber['attributes'][$item['label']] = $item['item_value'];
        }
//        $subscriber["global_attributes"] = $subscriber['attributes'];

        $client = $this->getRemoteClient();
        $response = $client->subscribe($subscriber);

        if (is_wp_error($response)) {
            // it's failed
            do_action('ff_log_data', [
                'parent_source_id' => $form->id,
                'source_type' => 'submission_item',
                'source_id' => $entry->id,
                'component' => $this->integrationKey,
                'status' => 'failed',
                'title' => $feed['settings']['name'],
                'description' => $response->errors['error'][0][0]['message']
            ]);
        } else {
            // It's success
            do_action('ff_log_data', [
                'parent_source_id' => $form->id,
                'source_type' => 'submission_item',
                'source_id' => $entry->id,
                'component' => $this->integrationKey,
                'status' => 'success',
                'title' => $feed['settings']['name'],
                'description' => 'Clever reach has been successfully initialed and pushed data'
            ]);
        }
    }
}
