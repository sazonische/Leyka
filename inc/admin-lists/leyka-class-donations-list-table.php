<?php if( !defined('WPINC') ) die;
/** Donors list table class */

if( !class_exists('WP_List_Table') ) {
    require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');
}

class Leyka_Admin_Donations_List_Table extends WP_List_Table {

    public function __construct() {

        parent::__construct(array('singular' => __('Donation', 'leyka'), 'plural' => __('Donations', 'leyka'), 'ajax' => true,));

        add_filter('leyka_admin_donations_list_filter', array($this, 'filter_donations'), 10, 2);

    }

    /**
     * WP_User & user meta fields filtering.
     *
     * @param $donations_params array
     * @param $filter_type string
     * @return array|false An array of get_users() params, or false if the $filter_type is wrong
     */
    public function filter_donations(array $donations_params, $filter_type = '') {

        /** @todo Implement the filtering... */

        return $donations_params;

    }

    /**
     * Retrieve donor’s data from the DB.
     *
     * @param int $per_page
     * @param int $page_number
     * @return mixed
     */
    public static function get_donations($per_page, $page_number = 1) {

        return Leyka_Donations::get_instance()->get(
            apply_filters('leyka_admin_donations_list_filter', array(
                'results_limit' => absint($per_page),
                'page' => absint($page_number),
                'orderby' => 'id',
                'order' => 'desc',
            ), 'get_donations')
        );

    }

    /**
     * Delete a donation record.
     *
     * @param int $donation_id Donation ID
     */
    public static function delete_donation($donation_id) {
        Leyka_Donations::get_instance()->delete_donation(absint($donation_id));
    }

    /**
     * @return int
     */
    public static function record_count() {
        return Leyka_Donations::get_instance()->get_count(
            apply_filters('leyka_admin_donations_list_filter', array(), 'get_donations_count')
        );
    }

    /** Text displayed when no data is available. */
    public function no_items() {
        _e('No donations avaliable.', 'leyka');
    }

    /**
     *  An associative array of columns.
     *
     * @return array
     */
    public function get_columns() {

        $columns = array(
            'cb' => '<input type="checkbox">',
            'donation_id' => __('ID'),
            'campaign' => __('Campaign', 'leyka'),
            'donor' => __('Donor', 'leyka'),
            'amount'=> __('Amount', 'leyka'),
            'gateway_pm' => __('Payment method', 'leyka'),
            'date' => __('Donation date', 'leyka'),
            'status' => __('Status', 'leyka'),
            'type' => __('Payment type', 'leyka'),
            'emails' => __('Email', 'leyka'),
            'donor_subscription' => __('Donor subscription', 'leyka'),
        );

        if(leyka_options()->opt('admin_donations_list_display') == 'separate-column') {
            $columns['amount_total'] = __('Total amount', 'leyka');
        }

        return apply_filters('leyka_admin_donations_columns_names', $columns);

    }

    /**
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'donation_id' => array('donation_id', true),
            'date' => array('date', true),
            'donor' => array('donor_name', true),
            'type' => array('type', true),
            'gateway_pm' => array('gateway_pm', true),
            'status' => array('status', true),
        );
    }

    /**
     * Render a column when no column specific method exists.
     *
     * @param array $donation
     * @param string $column_name
     * @return mixed
     */
    public function column_default($donation, $column_name) { /** @var $donation Leyka_Donation_Base */
        switch ($column_name) {
            case 'donation_id': return $donation->id;
            case 'type':
                return apply_filters(
                    'leyka_admin_donation_type_column_content',
                    '<i class="'.esc_attr($donation->payment_type).'">'.$donation->payment_type_label.'</i>',
                    $donation
                );
            case 'donor_comment':
                return apply_filters('leyka_admin_donation_donor_comment_column_content', $donation->donor_comment, $donation);
            case 'date':
            case 'donor_subscription':
                return $donation->$column_name;
            default:
                return LEYKA_DEBUG ? print_r($donation, true) : ''; // Show the whole array for troubleshooting purposes
        }
    }

    /**
     * Render the bulk edit checkbox.
     *
     * @param array $donation
     * @return string
     */
    public function column_cb($donation) { /** @var $donation Leyka_Donation_Base */
        return sprintf('<input type="checkbox" name="bulk-delete[]" value="%s">', $donation->id);
    }

    public function column_campaign($donation) { /** @var $donation Leyka_Donation_Base */

        $donation_edit_page = admin_url('?page=leyka_donations&donation_id='.$donation->id);
        $campaign = new Leyka_Campaign($donation->campaign_id);

        $column_content = '<div class="donation-campaign"><a href="'.$donation_edit_page.'">'.$campaign->title.'</a></div>'
            .'<div class="donation-email">'.$donation->donor_email.'</div>'
            .$this->row_actions(array(
//                'donor_page' => '<a href="'.$donation_edit_page.'">'.__('Edit').'</a>', /** @todo Until Donation edit page is ready */
                'delete' => sprintf(
                    '<a href="?page=%s&action=%s&donation=%s&_wpnonce=%s">'.__('Delete', 'leyka').'</a>',
                    esc_attr($_REQUEST['page']),
                    'delete',
                    $donation->id,
                    wp_create_nonce('leyka_delete_donation')
                ),
            ));

        return apply_filters('leyka_admin_donation_campaign_column_content', $column_content, $donation);

    }

    public function column_donor($donation) { /** @var $donation Leyka_Donation_Base */
        return apply_filters(
            'leyka_admin_donation_donor_column_content',
            '<div class="donor-name">'.$donation->donor_name.'</div><div class="donor-email">'.$donation->donor_email.'</div>',
            $donation
        );
    }

    public function column_amount($donation) { /** @var $donation Leyka_Donation_Base */

        if(leyka_options()->opt('admin_donations_list_display') === 'amount-column') {
            $amount = $donation->amount == $donation->amount_total ?
                $donation->amount :
                $donation->amount
                .'<span class="amount-total"> / '.$donation->amount_total.'</span>';
        } else {
            $amount = $donation->amount;
        }

        $column_content = '<span class="'.apply_filters('leyka_admin_donation_amount_column_css', ($donation->sum < 0 ? 'amount-negative' : 'amount')).'">'
            .apply_filters('leyka_admin_donation_amount_column_content', $amount.'&nbsp;'.$donation->currency_label, $donation)
            .'</span>';

        return apply_filters('leyka_admin_donation_amount_column_content', $column_content, $donation);

    }

    public function column_amount_total($donation) { /** @var $donation Leyka_Donation_Base */

        $column_content = '<span class="'.apply_filters('leyka_admin_donation_amount_total_column_css', $donation->amount_total < 0 ? 'amount-negative' : 'amount').'">'
            .apply_filters('leyka_admin_donation_amount_total_column_content', $donation->amount_total.'&nbsp;'.$donation->currency_label, $donation)
            .'</span>';

        return apply_filters('leyka_admin_donation_amount_total_column_content', $column_content, $donation);

    }

    public function column_gateway_pm($donation) { /** @var $donation Leyka_Donation_Base */

        $gateway_label = $donation->gateway_id ? $donation->gateway_label : __('Custom payment info', 'leyka');
        $pm_label = $donation->gateway_id ? $donation->pm_label : $donation->pm;

        return apply_filters(
            'leyka_admin_donation_gateway_pm_column_content',
            "<b>{$donation->payment_type_label}:</b> $pm_label <small>/ $gateway_label</small>",
            $donation
        );

    }

    public function column_status($donation) { /** @var $donation Leyka_Donation_Base */

        $status_info = leyka()->get_donation_status_info($donation->status);
        if( !$status_info ) { // Unknown status
            return '';
        }

        return apply_filters(
            'leyka_admin_donation_gateway_pm_column_content',
            '<i class="'.esc_attr($donation->status).'">'
                .$status_info['label'].'</i>&nbsp;<span class="dashicons dashicons-editor-help has-tooltip" title="'.$status_info['description'].'"></span>',
            $donation
        );

    }

    public function column_emails($donation) { /** @var $donation Leyka_Donation_Base */

        if($donation->donor_email_date){
            $column_content = str_replace(
                '%s',
                '<time>'.date(get_option('date_format').', H:i</time>', $donation->donor_email_date).'</time>',
                __('Sent at %s', 'leyka')
            );
        } else {
            $column_content = '<div class="leyka-no-donor-thanks" data-donation-id="'.$donation->id.'">'
                .sprintf(__('Not sent %s', 'leyka'), '<div class="send-donor-thanks">'.__('(send it now)', 'leyka').'</div>')
                .wp_nonce_field('leyka_donor_email', '_leyka_donor_email_nonce', false, true)
            .'</div>';
        }

        return apply_filters('leyka_admin_donation_emails_column_content', $column_content, $donation);

    }

    public function column_donor_subscription($donation) { /** @var $donation Leyka_Donation_Base */

        if($donation->donor_subscribed === true) {
            $column_content = '<div class="donor-subscription-status total">'.__('Full subscription', 'leyka').'</div>';
        } else if($donation->donor_subscribed > 0) {
            $column_content = '<div class="donor-subscription-status on-campaign">'
                .sprintf(__('On <a href="%s">campaign</a> news', 'leyka'), admin_url('post.php?post='.$donation->campaign_id.'&action=edit'))
            .'</div>';
        } else {
            $column_content = '<div class="donor-subscription-status none">'.__('None', 'leyka').'</div>';
        }

        return apply_filters('leyka_admin_donation_donor_subscribed_column_content', $column_content, $donation);

    }

    /**
     * Table filters panel.
     *
     * @param string $which "top" fro the upper panel or "bottom" for footer.
     */
    protected function extra_tablenav($which) {

        if($which !== 'top') {
            return;
        }?>

        <label for="payment-type-select"></label>
        <select id="payment-type-select" name="payment_type">
            <option value="" <?php echo empty($_GET['payment_type']) ? 'selected="selected"' : '';?>><?php _e('Select a payment type', 'leyka');?></option>

            <?php foreach(leyka_get_payment_types_data() as $payment_type => $label) {?>
                <option value="<?php echo $payment_type;?>" <?php echo !empty($_GET['payment_type']) && $_GET['payment_type'] == $payment_type ? 'selected="selected"' : '';?>><?php echo $label;?></option>
            <?php }?>
        </select>

        <label for="gateway-pm-select"></label>
        <select id="gateway-pm-select" name="gateway_pm">
            <option value="" <?php echo empty($_GET['gateway_pm']) ? '' : 'selected="selected"';?>><?php _e('Select a gateway or a payment method', 'leyka');?></option>

            <?php $gw_pm_list = array();
            foreach(leyka_get_gateways() as $gateway) {

                /** @var Leyka_Gateway $gateway */
                $pm_list = $gateway->get_payment_methods();
                if($pm_list)
                    $gw_pm_list[] = array('gateway' => $gateway, 'pm_list' => $pm_list);
            }
            $gw_pm_list = apply_filters('leyka_donations_list_gw_pm_filter', $gw_pm_list);

            foreach($gw_pm_list as $element) {?>

                <option value="<?php echo 'gateway__'.$element['gateway']->id;?>" <?php echo !empty($_GET['gateway_pm']) && $_GET['gateway_pm'] == 'gateway__'.$element['gateway']->id ? 'selected="selected"' : '';?>><?php echo $element['gateway']->name;?></option>

                <?php foreach($element['pm_list'] as $pm) {?>

                    <option value="<?php echo 'pm__'.$pm->id;?>" <?php echo !empty($_GET['gateway_pm']) && $_GET['gateway_pm'] == 'pm__'.$pm->id ? 'selected="selected"' : '';?>><?php echo '&nbsp;&nbsp;&nbsp;&nbsp;'.$pm->name;?></option>
                <?php }
            }?>

        </select>

        <?php $campaign_title = '';
        if( !empty($_GET['campaign']) && (int)$_GET['campaign'] > 0) {

            $campaign = get_post((int)$_GET['campaign']);
            if($campaign) {
                $campaign_title = $campaign->post_title;
            }

        }?>

        <label for="campaign-select"></label>
        <input id="campaign-select" type="text" data-nonce="<?php echo wp_create_nonce('leyka_get_campaigns_list_nonce');?>" placeholder="<?php _e('Select a campaign', 'leyka');?>" value="<?php echo $campaign_title;?>">
        <input id="campaign-id" type="hidden" name="campaign" value="<?php echo !empty($_GET['campaign']) ? (int)$_GET['campaign'] : '';?>">

        <label for="donor-subscribed-select"></label>
        <select id="donor-subscribed-select" name="donor_subscribed">
            <option value="-" <?php echo !isset($_GET['donor_subscribed']) ? 'selected="selected"' : '';?>><?php _e('Donors subscription', 'leyka');?></option>
            <option value="1" <?php echo isset($_GET['donor_subscribed']) && $_GET['donor_subscribed'] ? 'selected="selected"' : '';?>><?php _e('Subscribed donors', 'leyka');?></option>
            <option value="0" <?php echo isset($_GET['donor_subscribed']) && !$_GET['donor_subscribed'] ? 'selected="selected"' : '';?>><?php _e('Unsubscribed donors', 'leyka');?></option>
        </select>

        <input type="submit" class="button action" name="leyka-donations-filter" value="<?php _e('Filter', 'leyka');?>">

    <?php return;

    }

    protected function get_views() {

        $base_page_url = admin_url('admin.php?page=leyka_donations');
        $links = array('all' => '<a href="'.$base_page_url.'">'.__('All').'</a>');

        foreach(leyka_get_donation_status_list(false) as $status => $label) { // Remove "false" when "trash" status is in use
            $links[$status] = '<a href="'.$base_page_url.'&status='.$status.'">'.$label.'</a>';
        }

        return $links;

    }

    /**
     * Data query, filtering, sorting & pagination handler.
     */
    public function prepare_items() {

        $this->_column_headers = $this->get_column_info();

        $this->process_bulk_action();

        $per_page = $this->get_items_per_page('donations_per_page');

        $this->set_pagination_args(array('total_items' => self::record_count(), 'per_page' => $per_page,));

        $this->items = self::get_donations($per_page, $this->get_pagenum());

    }

    /**
     * @return array
     */
    public function get_bulk_actions() {
        return array('bulk-delete' => __('Delete'));
    }

    public function process_bulk_action() {

        if('delete' === $this->current_action()) { // Single donation deletion

            if( !wp_verify_nonce(esc_attr($_REQUEST['_wpnonce']), 'leyka_delete_donation') ) {
                die(__("You don't have permissions for this operation.", 'leyka'));
            } else {
                self::delete_donation(absint($_GET['donation']));
            }

        }

        if( // Bulk donations deletion
            (isset($_POST['action']) && $_POST['action'] === 'bulk-delete')
            || (isset($_POST['action2']) && $_POST['action2'] === 'bulk-delete')
        ) {
            foreach(esc_sql($_POST['bulk-delete']) as $donation_id) {
                self::delete_donation($donation_id);
            }
        }

    }

}