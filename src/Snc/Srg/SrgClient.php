<?php
/**
 * Created by PhpStorm.
 * User: adminuser
 * Date: 20.05.15
 * Time: 12:09
 */

namespace Snc\Srg;

use Snc\Common\Enum\ClientOptions as Options;
use Guzzle\Http\Client;
use Guzzle\Http\EntityBody;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Snc\Srg\Exception\CyclicRequestTokenException;
use Snc\Srg\Exception\InvalidContactingException;
use Snc\Srg\Exception\InvalidArgumentException;

class SrgClient extends Client
{
    const LATEST_API_VERSION = '2015-05-20';
    protected $directory = __DIR__;

    protected $key;
    protected $secret;
    protected $token;
    protected $ticket;
    protected $configure = [
        'base_url'  => 'https://storencash.com/api/',
        'session_name' => 'snc_auth_token',
        'auth'      => 'v1_ticket.json',
        'remains'   => 'v1_remains.json',
        'product'   => 'v1_product.json',
        'groups'    => 'v1_groups.json',
        'measure'   => 'v1_measure.json',
        'operation' => 'v1_operation.json',
        'store'     => 'v1_storage.json',
        'company'   => 'v1_sabor.json',
        'transactions' => 'v1_transaction.json',
    ];

    protected $loop;

    /** @var SrgClient  */
    private $client;

    public function __construct($config)
    {
        $this->setConfigure(array_merge($this->getConfigure(), $config));
        parent::__construct($this->getConfigure()[Options::BASE_URL]);
        $this->loop = 0;
        $this->setClient($this);
        /** Init and save token in session */
        $this->getSessionToken();
    }

    /**
     * Получение остатков
     * @param $params
     * @return mixed
     * @throws InvalidContactingException
     * @link https://gist.github.com/stepun/d294d595dfed3b791e56
     */
    function getRemains($params)
    {
        if (!empty($params)) {
            if (isset($params['id'])) {
                /**
                 * @link https://gist.github.com/stepun/d294d595dfed3b791e56#remains-by-id
                 * @var Response $response
                 */
                $response = $this->getApi($this->getConfigure()[Options::API_URL_REMAINS] . '/' . $params['id']);
            } else if (isset($params['storage_id'])) {
                /**
                 * @link https://gist.github.com/stepun/d294d595dfed3b791e56#list-remains-by-store
                 * @var Response $response
                 */
                $response = $this->getApi($this->getConfigure()[Options::API_URL_REMAINS], $params);
            } else if (isset($params['product_id'])) {
                if (isset($params['group_id'])) {
                    /**
                     * Получение остатков в разрезе номенклатуры по выбранной группе (товаров в размере)
                     * @var Response $response
                     */
                    $response = $this->getApi($this->getConfigure()[Options::API_URL_REMAINS], array_merge(['action' => 'byGroupId'], $params));
                } else {
                    /**
                     * Получение остатков в разрезе номенклатуры
                     * @var Response $response
                     */
                    $response = $this->getApi($this->getConfigure()[Options::API_URL_REMAINS], array_merge(['action' => 'byProductId'], $params));
                }
            } else if (isset($params['listByPG'])) {
                /**
                 * Групповая операция
                 * Получение остатков в разрезе номенклатуры по выбранной группе (товаров в размере)
                 */
                $response = $this->postApi(
                    $this->getConfigure()[Options::API_URL_REMAINS],
                    ['action' => 'listbyGroupId'],
                    ['list' => $params['listByPG']]
                );
            } else {
                throw new InvalidArgumentException('Parameters is wrong! '
                    . 'Valid params: 1. {id int(10)} - for get remains by ID; '
                    . '2. {storage_id int(10)} get remains by store if storage_id == 0 - gel ALL remains in ALL stores. '
                    . 'See the API documentation https://gist.github.com/stepun/d294d595dfed3b791e56');
            }
            $body = json_decode($response->getBody(true), true);
            return $this->_verifyStatus($body, $params, 'getRemains');

        } else {
            throw new InvalidArgumentException('Parameters can not be empty!');
        }
    }

    public function getGroups($params = [])
    {
        if (!empty($params)) { /** Добавление значения группы */

        } else { /** Запрос списка групп */
            /**
             * @link https://gist.github.com/stepun/abc58553c1a7c98640dd#list-groups
             * @var Response $response
             */
            $response = $this->getApi($this->getConfigure()[Options::API_URL_GROUPS]);
        }
        $body = json_decode($response->getBody(true), true);
        return $this->_verifyStatus($body, $params, 'getGroups');
    }

    /**
     * Public method getApi with verify token
     * @param $url
     * @param $params
     * @return mixed
     */
    public function getAPIData($url, $params = [])
    {
        $response = $this->getApi($url, $params);
        $body = json_decode($response->getBody(true), true);
        if (!empty($body['status'])) {
            if ($body['status'] == 'fail' || $body['status'] == 'error') {
                if (!empty($body['code']) && ($body['code'] == 418 || $body['code'] == 401) ) {
                    $this->loop++;
                    $this->requestToken();
                    if ($this->loop < 10) {
                        return $this->getAPIData($url, $params);
                    } else {
                        throw new CyclicRequestTokenException('Error cyclic request ticket to the server');
                    }
                }
            }
            return $body;
        } else {
            throw new InvalidContactingException('Error contacting the server');
        }
    }

    /**
     * Public method postApi with verify token
     *
     * @param $url
     * @param array $params
     * @param array $body
     * @param array $fields
     * @return array|mixed
     */
    public function postAPIData($url, $params = [], $body = [], $fields = [])
    {
        $response = $this->postApi($url, $params, $body, $fields);
        $body = json_decode($response->getBody(true), true);
        if (!empty($body['status'])) {
            if ($body['status'] == 'fail' || $body['status'] == 'error') {
                if (!empty($body['code']) && ($body['code'] == 418 || $body['code'] == 401) ) {
                    $this->loop++;
                    $this->requestToken();
                    if ($this->loop < 10) {
                        return $this->postAPIData($url, $params, $body, $fields);
                    } else {
                        throw new CyclicRequestTokenException('Error cyclic request ticket to the server');
                    }
                }
            }
            return $body;
        } else {
            throw new InvalidContactingException('Error contacting the server');
        }
    }

    /**
     * Public method putApi with verify token
     * @param $url
     * @param array $params
     * @param array $body
     * @param array $fields
     * @return array|mixed
     */
    public function putAPIData($url, $params = [], $body = [], $fields = [])
    {
        $response = $this->putApi($url, $params, $body, $fields);
        $body = json_decode($response->getBody(true), true);
        if (!empty($body['status'])) {
            if ($body['status'] == 'fail' || $body['status'] == 'error') {
                if (!empty($body['code']) && ($body['code'] == 418 || $body['code'] == 401) ) {
                    $this->loop++;
                    $this->requestToken();
                    if ($this->loop < 10) {
                        return $this->putAPIData($url, $params, $body, $fields);
                    } else {
                        throw new CyclicRequestTokenException('Error cyclic request ticket to the server');
                    }
                }
            }
            return $body;
        } else {
            throw new InvalidContactingException('Error contacting the server');
        }
    }

    /**
     * Checking the status of the response from a remote server
     *
     * @param $body
     * @param $params
     * @param $method
     * @return mixed
     * @throws Exception\CyclicRequestTokenException
     * @throws Exception\InvalidContactingException
     */
    private function _verifyStatus($body, $params, $method)
    {
        if (!empty($body['status'])) {
            if ($body['status'] == 'fail' || $body['status'] == 'error') {
                if (!empty($body['code']) && ($body['code'] == 418 || $body['code'] == 401) ) {
                    $this->loop++;
                    $this->requestToken();
                    if ($this->loop < 10) {
                        return $this->$method($params);
                    } else {
                        throw new CyclicRequestTokenException('Error cyclic request ticket to the server');
                    }
                }
            }
            return $body;
        } else {
            throw new InvalidContactingException('Error contacting the server');
        }
    }

    /**
     * Factory method to create a new Store & Cash Srg client using an array of configuration options.
     *
     * @param array|Collection $config Client configuration data
     *
     * @return SrgClient
     */
    public static function factory($config = array())
    {
        $client = new static($config);
        return $client;
    }

    function getSessionToken()
    {
        $sessionStatus = session_status();
        switch ($sessionStatus) {
            default:
            case PHP_SESSION_DISABLED: /** if session disabled */
            case PHP_SESSION_NONE: /** if session enabled but not create */
                if (session_start()) {
                    $token = (!empty($_SESSION[$this->getConfigure()['session_name']]) ? $_SESSION[$this->getConfigure()['session_name']] : false);
                } else {
                    $token = false;
                }
                break;
            case PHP_SESSION_ACTIVE: /** if session enabled and create */
                $token = (!empty($_SESSION[$this->getConfigure()['session_name']]) ? $_SESSION[$this->getConfigure()['session_name']] : false);
                break;
        }
        if (!$token) { /** get request new token */
            $token = $this->requestToken();
            if (!empty($token)) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION[$this->getConfigure()['session_name']] = $token;
                }
            }
        }
        $this->setToken($token);
        return $token;
    }

    /**
     * Запрос нового ticket для генерации нового token
     */
    function requestToken()
    {
        $request = $this->getClient()->put($this->getConfigure()[Options::API_URL_AUTH] . '/' . $this->getConfigure()[Options::PUBLIC_KEY]);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $response = $request->send();
        $body = json_decode($response->getBody(true), true);

        if (!empty($body['status'])) {
            if ($body['status'] == 'success') {
                $this->setTicket($body['data']['ticket']);
                $token = $this->generateToken($this->getConfigure()[Options::PRIVATE_KEY], $this->getTicket());
                $this->setToken($token);
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION[$this->getConfigure()['session_name']] = $token;
                }
                return $token;
            } else {
                //Ошибка формирования ticket
                throw new InvalidArgumentException('Error creating ticket');
            }
        } else {
            //Ошибка обращения к серверу
            throw new InvalidContactingException('Error contacting the server');
        }
    }

    /**
     * Отправка GET API запроса
     * @param $url
     * @param array $params
     * @return Response
     */
    public function getApi($url, $params = [])
    {
        $request = $this->getClient()->get($url);
        if (!empty($params)) {
            $query = $request->getQuery();
            foreach ($params as $key => $value) {
                $query->add($key, $value);
            }
        }
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $request->setHeader('token', $this->getToken());
        return $request->send();
    }

    /**
     * Отправка POST API
     * @param $url
     * @param array $params
     * @param array $body
     * @return Response
     */
    public function postApi($url, $params = [], $body = [], $fields = [])
    {
        $request = $this->getClient()->post($url, null, $body);
        if (!empty($params)) {
            $query = $request->getQuery();
            foreach ($params as $key => $value) {
                $query->add($key, $value);
            }
        }
        if (!empty($fields)) {
            $request->addPostFields($fields);
        }
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $request->setHeader('token', $this->getToken());
        return $request->send();
    }

    /**
     * Отправка PUT API
     * @param $url
     * @param array $params
     * @param array $body
     * @return Response
     */
    public function putApi($url, $params = [], $body = [], $fields = [])
    {
        $request = $this->getClient()->put($url, null, $body);
        if (!empty($params)) {
            $query = $request->getQuery();
            foreach ($params as $key => $value) {
                $query->add($key, $value);
            }
        }
        if (!empty($fields)) {
            $request->addPostFields($fields);
        }
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $request->setHeader('token', $this->getToken());
        return $request->send();
    }

    /**
     * Отправка DELETE API
     * @param $url
     * @return Response
     */
    public function deleteApi($url)
    {
        $request = $this->getClient()->delete($url);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, false);
        $request->getCurlOptions()->set(CURLOPT_SSL_VERIFYHOST, false);
        $request->setHeader('token', $this->getToken());
        return $request->send();
    }


    function generateToken($secret, $ticket)
    {
        return md5($secret . $ticket);
    }

    /**
     * @param mixed $client
     * @return $this
     */
    public function setClient($client)
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @return SrgClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param mixed $key
     * @return $this
     */
    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param mixed $secret
     * @return $this
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @param mixed $ticket
     * @return $this
     */
    public function setTicket($ticket)
    {
        $this->ticket = $ticket;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTicket()
    {
        return $this->ticket;
    }

    /**
     * @param mixed $token
     * @return $this
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param mixed $config
     * @return $this
     */
    public function setConfigure($config)
    {
        $this->configure = $config;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getConfigure()
    {
        return $this->configure;
    }
} 