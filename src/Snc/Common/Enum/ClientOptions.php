<?php
/**
 * Created by PhpStorm.
 * User: adminuser
 * Date: 20.05.15
 * Time: 12:58
 */
namespace Snc\Common\Enum;

class ClientOptions
{
    /**
     * SnC Access Key ID
     *
     * @deprecated
     */
    const PUBLIC_KEY = 'key';

    /**
     * SnC secret access key
     *
     * @deprecated
     */
    const PRIVATE_KEY = 'secret';

    /**
     * Custom SnC security token to use with request authentication.
     *
     * @deprecated
     */
    const TOKEN = 'token';

    /**
     * Custom SnC security ticket to use with secret access key for generate token.
     *
     */
    const TICKET = 'ticket';

    /**
     * @var string API version used by the client
     */
    const VERSION = 'version';

    const SCHEME = 'scheme';
    const BASE_URL = 'base_url';

    const
        API_URL_AUTH          = 'auth',
        API_URL_REMAINS       = 'remains',
        API_URL_PRODUCT       = 'product',
        API_URL_TRANSACTIONS  = 'transactions',
        API_URL_GROUPS        = 'groups',
        API_URL_MEASURE       = 'measure',
        API_URL_OPERATIONS    = 'operations',
        API_URL_STORES        = 'stores',
        API_URL_COMPANIES     = 'companies',
        API_URL_CATEGORY      = 'category';
}