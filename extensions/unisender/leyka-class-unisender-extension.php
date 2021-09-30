<?php if ( !defined('WPINC') ) die;

/**
 * Leyka Extension: Unisender integration
 * Version: 1.0
 * Author: Teplitsa of social technologies
 * Author URI: https://te-st.ru
 **/

class Leyka_Unisender_Extension extends Leyka_Extension {

    protected static $_instance;

    protected function _set_attributes() {

        $this->_id = 'unisender';
        $this->_title = __('Unisender', 'leyka');
        $this->_description = __('This extension provides an integration with the Unisender service.', 'leyka');
        $this->_full_description = __('The extension provides an automatic subscrition of the donors to Unisender mailing lists of your choice.', 'leyka');
        $this->_settings_description = '';
        $this->_connection_description = __(
            '<h4>Short instruction:</h4>
            <div>
                <ol>
                    <li>Register the Unisender account</li>
                    <li>Create one or more mailing lists and save their IDs in extension settings</li>
                    <li>Copy API key from Unisender personal account to extension settings</li>
                    <li>Select needed donor fields</li>
                </ol>
            </div>'
        , 'leyka'); // TODO Заменить до релиза
        $this->_user_docs_link = 'https://leyka.te-st.ru/docs/unisender/';
        $this->_has_wizard = false;
        $this->_has_color_options = false;
        $this->_icon = LEYKA_PLUGIN_BASE_URL.'extensions/unisender/img/main_icon.jpeg';

    }

    protected function _set_options_defaults() {

        $this->_options = apply_filters('leyka_'.$this->_id.'_extension_options', [
            ['section' => [
                'name' => $this->_id.'-main-options',
                'title' => __('Main options', 'leyka'),
                'options' => [
                    $this->_id.'_api_key' => [
                        'type' => 'text',
                        'title' => __('API key', 'leyka'),
                        'comment' => __('"Unisender" API key', 'leyka'),
                        'required' => true,
                        'is_password' => true,
                        'placeholder' => sprintf(__('E.g., %s', 'leyka'), 'abcdefghijklmnopqrstuvwxyz1234567890'),
                    ],
                    $this->_id.'_lists_ids' => [
                        'type' => 'text',
                        'title' => __('IDs of the "Unisender" lists to subscribe', 'leyka'),
                        'comment' => __('IDs of the lists (in "Unisender") that holds donors contacts', 'leyka'),
                        'required' => true,
                        'placeholder' => sprintf(__('E.g., %s', 'leyka'), '1,3,10'),
                        'description' => __('Comma-separated IDs list', 'leyka'),
                    ],
                    $this->_id.'_donor_fields' => [
                        'type' => 'multi_select',
                        'title' => __('Donor fields', 'leyka'),
                        'required' => true,
                        'comment' => __('Donor fields which will be transferred to Unisender', 'leyka'),
                        'list_entries' => $this->_get_donor_fields(),
                        'default' => ['name'], // 'default' should be an array of values (even if it's single value there)
                        'description' => __('Hint: to capture your data Unisender list should have fields with this variables -', 'leyka')
                    ],
                    $this->_id.'_donor_confirmation' => [
                        'type' => 'checkbox',
                        'default' => true,
                        'title' => __('Donor subscription confirmation', 'leyka'),
                        'comment' => __('If enabled, donors will be asked by email for permission upon subscribing on the list', 'leyka'),
                        'short_format' => true
                    ],
                    $this->_id.'_donor_overwrite' => [
                        'type' => 'select',
                        'title' => __('Overwrite donor information', 'leyka'),
                        'comment' => __('If donor already registered in "Unisender"', 'leyka'),
                        'default' => '0',
                        'list_entries' => [
                            '0' => __('New fields will be created, old fields will not get new values.', 'leyka'),
                            '1' => __('Old donor data will be fully dropped, new data will be stored.', 'leyka'),
                            '2' => __('New fields will be created, old ones will be refreshed. Unaffected fields will keep their data.', 'leyka')
                        ]
                    ]
                ]
            ]],
            ['section' => [
                'name' => $this->_id.'-logs',
                'title' => __('Unisender logs', 'leyka'),
                'is_default_collapsed' => true,
                'options' => [
                    $this->_id.'_error_log' => [
                        'type' => 'static_text',
                        'title' => __('Subscription error log', 'leyka'),
                        'is_html' => true,
                        'value' => $this->_render_unisender_error_log()
                    ]
                ]
            ]]
        ]);

    }

    protected function _get_donor_fields() {

        $fields_library = leyka_options()->opt('additional_donation_form_fields_library');
        $additional_fields = ['name' => __('Name', 'leyka')];

        foreach ($fields_library as $name => $data) {
            $additional_fields[$name] = __($data['title'], 'leyka');
        }

        return $additional_fields;

    }

    protected function _initialize_active() {

        add_action(
            get_plugin_page_hookname('leyka_donation_info', null),
            [$this, '_show_subscription_result_metabox']
        );
        add_action('admin_enqueue_scripts', [$this, '_load_admin_assets']);
        add_action('leyka_donation_funded_status_changed', [$this, '_add_donor_to_unisender_list'], 11, 3);
        add_filter('leyka_js_localized_strings', [$this, '_localize_js_strings']);

    }

    public function _add_donor_to_unisender_list($donation_id, $old_status, $new_status) {

        if($old_status !== 'funded' && $new_status === 'funded') {

            require_once LEYKA_PLUGIN_DIR.'extensions/unisender/lib/UnisenderApi.php';

            $api_key = leyka_options()->opt($this->_id.'_api_key');
            $donation = Leyka_Donations::get_instance()->get($donation_id);
            $donor_fields = ['email' => $donation->donor_email];

            foreach(leyka_options()->opt($this->_id.'_donor_fields') as $field_name) {

                if($field_name === 'name') {
                    $donor_fields[$field_name] = $donation->donor_name;
                } else {

                    $donation_additional_fields = $donation->additional_fields;

                    if( !empty($donation_additional_fields[$field_name]) ) {
                        $donor_fields[$field_name] = $donation_additional_fields[$field_name];
                    }

                }

            }

            $uni = new \Unisender\ApiWrapper\UnisenderApi($api_key);
            $result = $uni->subscribe([
                'list_ids' => str_replace(' ','', stripslashes(leyka_options()->opt($this->_id.'_lists_ids'))),
                'fields' =>  $donor_fields,
                'double_optin' => leyka_options()->opt($this->_id.'_donor_confirmation') === '1' ? 4 : 3,
                'overwrite' => leyka_options()->opt($this->_id.'_donor_overwrite')
            ]);

            $result_array = json_decode($result, true);
            $donation->set_meta('unisender_subscription_response', $result_array);

            if( !empty($result_array['error']) ) {

                $error_log = !get_option('leyka_unisender_error_log') ? [] : get_option('leyka_unisender_error_log');
                $result_array['date'] = date('d.m.Y H:i');

                $error_log[] = json_encode($result_array);

                if(sizeof($error_log) > 10) {
                    array_shift($error_log);
                }

                update_option('leyka_unisender_error_log', $error_log, 'no');

            }

        }

    }

    public function _render_unisender_error_log() {

        if( !get_option('leyka_unisender_error_log') || !is_array(get_option('leyka_unisender_error_log')) ) {
            return '';
        }

        $error_log = get_option('leyka_unisender_error_log');
        foreach($error_log as $index => $error_data_str) {

           $error_data = json_decode($error_data_str, true);
           $error_log_text .= !empty($error_data['error']) && !empty($error_data['date']) ?
                '<li><b>('.$error_data['date'].')</b> '.$error_data['error'].'</li>' : '';
        }

        return '<ul>'.$error_log_text.'</ul>';

    }

    public function _show_subscription_result_metabox() {

        if( !empty($_GET['donation']) ) { // Edit Donation page

            $donation_id = $_GET['donation'];
            $donation = Leyka_Donations::get_instance()->get($donation_id);

            if( !empty($donation->get_meta('unisender_subscription_response')) ) {
                add_meta_box(
                    'leyka_donation_unisender_subscription_response',
                    __('Unisender subscription response', 'leyka'),
                    [$this, '_render_subscription_response'],
                    'dashboard_page_leyka_donation_info',
                    'normal',
                    'low'
                );
            }

        }

    }

    public function _render_subscription_response() {

        $donation = Leyka_Donations::get_instance()->get(absint($_GET['donation']));
        $subscription_response = $donation->get_meta('unisender_subscription_response');

        if( !empty($subscription_response['error']) ) {
            echo '<div><b>'.__('Error', 'leyka').': </b>'.$subscription_response['error'].'</div>';
        } else if( !empty($subscription_response['result']) && !empty($subscription_response['result']['person_id']) ) {
            echo
                '<div>
                    <b>'.__('Subscribed user ID', 'leyka').': </b>'.$subscription_response['result']['person_id'].'</br>
                    <b>'.__('Invitation letter', 'leyka').': </b>'.(isset($subscription_response['result']['invitation_letter_sent']) ? 'Yes' : 'No').'</br>
                </div>';
        } else {
            echo '<div>'.__('Response data is not correct', 'leyka').'</div>';
        }

    }

    public function _load_admin_assets() {

        wp_enqueue_style(
            $this->_id.'-admin',
            self::get_base_url().'/assets/css/admin.css',
            [],
            defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ? uniqid() : null
        );

        wp_enqueue_script(
            $this->_id.'-admin',
            self::get_base_url().'/assets/js/admin.js',
            ['jquery']
        );

    }

    public function _localize_js_strings($strings) {

        $strings['donor_fields_description_hint'] = __('Hint: to capture your data Unisender list should have fields with this variables -', 'leyka');

        return $strings;

    }

}

function leyka_add_extension_unisender() {
    leyka()->add_extension(Leyka_Unisender_Extension::get_instance());
}
add_action('leyka_init_actions', 'leyka_add_extension_unisender');