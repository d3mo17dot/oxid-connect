<?php

use Makaira\Connect\Result\Error;
use Makaira\Connect\Result\ForbiddenException;

class makaira_connect_endpoint extends oxUBase
{
    protected $statusCodes = array(
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        426 => 'Upgrade Required',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended',
    );

    /**
     * Main render method
     *
     * Called by oxid – is supposed to render a Smarty template. In our case it
     * just returns a JSON response and dies afterwards.
     *
     * @return void
     */
    public function render()
    {
        ini_set('html_errors', false);
        header("Content-Type: application/json");

        if (!isset($_SERVER['HTTP_X_MAKAIRA_NONCE']) || !isset($_SERVER['HTTP_X_MAKAIRA_HASH'])) {
            $this->setStatusHeader(401);
            echo json_encode(new Error('Unauthorized'));
            exit();
        }

        if (!$this->verifySharedSecret()) {
            $this->setStatusHeader(403);
            echo json_encode(new Error('Forbidden'));
            exit();
        }

        try {
            $updates = $this->getUpdatesAction();
            echo json_encode($updates);
        } catch (\Exception $e) {
            $this->setStatusHeader(500);
            $error = new Error($e->getMessage());

            if (!oxRegistry::getConfig()->isProductiveMode()) {
                $error->file = $e->getFile();
                $error->line = $e->getLine();
                $error->stack = explode(PHP_EOL, $e->getTraceAsString());
            }

            echo json_encode($error);
        }

        exit();
    }

    protected function verifySharedSecret()
    {
        $nonce  = isset($_SERVER['HTTP_X_MAKAIRA_NONCE']) ? $_SERVER['HTTP_X_MAKAIRA_NONCE'] : null;
        $hash   = isset($_SERVER['HTTP_X_MAKAIRA_HASH']) ? $_SERVER['HTTP_X_MAKAIRA_HASH'] : null;
        $secret = oxRegistry::getConfig()->getShopConfVar('makaira_connect_secret');
        $body   = file_get_contents('php://input');

        return ($hash === hash_hmac('sha256', $nonce . ':' . $body, $secret));
    }

    protected function setStatusHeader($statusCode)
    {
        if (isset($this->statusCodes[$statusCode])) {
            $string = $statusCode . ' ' . $this->statusCodes[$statusCode];
            header('HTTP/1.1 ' . $string, true, $statusCode);
        } else {
            $this->setStatusHeader(500);
        }
    }

    public function getUpdatesAction()
    {
        /** @var \Marm\Yamm\DIC $dic */
        $dic = oxRegistry::get('yamm_dic');

        $body = json_decode(file_get_contents('php://input'));
        if (($body === null) || (!property_exists($body, 'since'))) {
            throw new \RuntimeException("Failed to decode request body");
        }

        $language = $this->getConfig()->getRequestParameter('language');
        if (!isset($language)) {
            $language = oxRegistry::getLang()->getLanguageAbbr();
        }

        /** @var \Makaira\Connect\Utils\TableTranslator $translator */
        $translator = $dic['oxid.table_translator'];
        $translator->setLanguage($language);

        /** @var \Makaira\Connect\Repository $repository */
        $repository = $dic['makaira.connect.repository'];

        return $repository->getChangesSince($body->since, isset($body->count) ? $body->count : 50);
    }
}