<?php
/**
 * UalaBis Gateway.
 * Checkout API
 * Docs. https://developers.ualabis.com.ar/api-checkout
 */
class Ualabis extends NonmerchantGateway
{
    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * Construct a new non-merchant gateway.
     */
    public function __construct()
    {
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load components required by this gateway
        Loader::loadComponents($this, ['Input']);

        // Load the language required by this gateway
        Language::loadLang(
            'ualabis',
            null,
            dirname(__FILE__) . DS . 'language' . DS
        );
    }

    /**
     * Sets the currency code to be used for all subsequent payments.
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Create and return the view content required to modify the settings of this gateway.
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        $this->view = $this->makeView(
            'settings',
            'default',
            str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS)
        );

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('meta', $meta);

        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway.
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        // Verify meta data is valid
        $rules = [
            'user_name' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Ualabis.!error.user_name.empty', true)
                ]
            ],
            'client_id' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Ualabis.!error.client_id.empty', true)
                ]
            ],
            'client_secret_id' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Ualabis.!error.client_secret_id.empty', true)
                ]
            ],
            'grant_type' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Ualabis.!error.grant_type.empty', true)
                ]
            ]
        ];

        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);

        // Return the meta data, no changes required regardless of success or failure for this gateway
        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database.
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return ['user_name','client_id', 'client_secret_id', 'grant_type'];
    }

    /**
     * Sets the meta data for this particular gateway.
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Returns all HTML markup required to render an authorization and capture payment form.
     *
     * @param array $contact_info An array of contact info including:
     *  - id The contact ID
     *  - client_id The ID of the client this contact belongs to
     *  - user_id The user ID this contact belongs to (if any)
     *  - contact_type The type of contact
     *  - contact_type_id The ID of the contact type
     *  - first_name The first name on the contact
     *  - last_name The last name on the contact
     *  - title The title of the contact
     *  - company The company name of the contact
     *  - address1 The address 1 line of the contact
     *  - address2 The address 2 line of the contact
     *  - city The city of the contact
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-cahracter country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the contact
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *  - description The Description of the charge
     *  - return_url The URL to redirect users to after a successful payment
     *  - recur An array of recurring info including:
     *      - start_date The date/time in UTC that the recurring payment begins
     *      - amount The amount to recur
     *      - term The term to recur
     *      - period The recurring period (day, week, month, year, onetime) used in
     *          conjunction with term in order to determine the next recurring payment
     * @return mixed A string of HTML markup required to render an authorization and
     *  capture payment form, or an array of HTML markup
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
        $api = $this->getApi($this->meta['user_name'], $this->meta['client_id'], $this->meta['client_secret_id'], $this->meta['grant_type']);

        // Set redirect url
        $redirect_url = ($options['return_url'] ?? null);
        $query = parse_url($redirect_url, PHP_URL_QUERY);
        $invoices = $this->serializeInvoices($invoice_amounts);

        if ($query) {
            $redirect_url .= '&';
        } else {
            $redirect_url .= '?';
        }
        $redirect_url .= 'amount=' . $amount . '&invoices=' . $invoices;

        $params = [
            'amount' => (string)$amount,
            'description' => $options['description'] ?? null,
            'userName' => $this->meta['user_name'],
            'callback_fail' => $redirect_url,
            'callback_success' => $redirect_url,
            'notification_url' => Configure::get('Blesta.gw_callback_url') . Configure::get('Blesta.company_id') .
                '/ualabis/?client_id=' . ($contact_info['client_id'] ?? ''),
        ];

        $tokenize = $api->buildToken();
        $token = $tokenize->data();

        // Get the url to redirect the client to
        $result = $api->buildPayment($params, $token->access_token);
        $data = $result->data();

        $ualabis_url = $data->links->checkoutLink ?? '';

        return $this->buildForm($ualabis_url);
    }

    /**
     * Builds the HTML form.
     *
     * @return string The HTML form
     */
    private function buildForm($post_to)
    {
        $this->view = $this->makeView(
            'process',
            'default',
            str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS)
        );

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('post_to', $post_to);

        return $this->view->fetch();
    }

    /**
     * Validates the incoming POST/GET response from the gateway to ensure it is
     * legitimate and can be trusted.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's
     *      original transaction (in the case of refunds)
     */
    public function validate(array $get, array $post)
    {
        $api = $this->getApi($this->meta['user_name'], $this->meta['client_id'], $this->meta['client_secret_id'], $this->meta['grant_type']);
        $callback_data = json_decode(@file_get_contents('php://input'));

        // Log request received
        $this->log(
            isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null,
            json_encode(
                $callback_data,
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ),
            'output',
            true
        );

        // Log data sent for validation
        $this->log(
            'validate',
            json_encode(
                ['order_id' => $callback_data->uuid],
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ),
            'output',
            true
        );

        $tokenize = $api->buildToken();
        $token = $tokenize->data();

        $result = $api->checkPayment($callback_data->uuid, $token->access_token);
        $data = $result->data();

        // Log post-back sent
        $this->log(
            'validate',
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'input',
            true
        );

        $status = 'error';
        switch ($data->status ?? null) {
            case 'APPROVED':
                $status = 'approved';
                break;
            case 'PENDING':
                $status = 'pending';
                break;
            case 'PROCESSED':
                $status = 'approved';
                break;
            case 'REJECTED':
                $status = 'declined';
                break;
        }

        return [
            'client_id' => $get['client_id'] ?? null,
            'amount' => $data->amount ?? 0,
            'currency' => 'ARS',
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => $data->order_id ?? null,
            'invoices' => $this->unserializeInvoices($get['invoices']) ?? null
        ];
    }

    public function success(array $get, array $post)
    {
        return [
            'client_id' => $get['client_id'] ?? null,
            'amount' => $get['amount'] ?? 0,
            'currency' => 'ARS',
            'status' => 'approved',
            'reference_id' => null,
            'transaction_id' => null,
            'invoices' => $this->unserializeInvoices($get['invoices']) ?? null
        ];
    }

    private function serializeInvoices(array $invoices)
    {
        $str = '';
        foreach ($invoices as $i => $invoice) {
            $str .=
                 ($i > 0 ? '|' : '') . $invoice['id'] . '=' . $invoice['amount'];
        }

        return $str;
    }

    /**
     * Unserializes a string of invoice info into an array.
     *
     * @param string A serialized string of invoice info in the format of key1=value1|key2=value2
     * @param mixed $str
     * @return array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     */
    private function unserializeInvoices($str)
    {
        $invoices = [];
        $temp = explode('|', $str);
        foreach ($temp as $pair) {
            $pairs = explode('=', $pair, 2);
            if (count($pairs) != 2) {
                continue;
            }
            $invoices[] = ['id' => $pairs[0], 'amount' => $pairs[1]];
        }

        return $invoices;
    }

    /**
     * Initializes the UalaBis API and returns an instance of that object with the given account information set.
     *
     * @return UalabisApi A UalaBis instance
     */
    private function getApi($user_name, $client_id, $client_secret_id, $grant_type)
    {
        // Load library methods
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'ualabis_api.php');

        return new UalabisApi($user_name, $client_id, $client_secret_id, $grant_type);
    }
}
