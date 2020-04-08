<?php
/**
 * Created by PhpStorm.
 * User: alt
 * Date: 17-Oct-18
 * Time: 9:41 PM
 */

namespace As247\Flysystem\OneDrive;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class TokenHelper
{
    protected $clientId;
    protected $clientSecret;
    protected $accessToken;
    protected $refreshToken;
    protected $httpClient;
    /***
     * @var \Illuminate\Cache\CacheManager
     */
    protected $cache;
    protected $tokenEndpoint='https://login.microsoftonline.com/7c1d248e-7149-45f7-a26d-626430f958d4/oauth2/v2.0/token';
    public function __construct($clientId='',$clientSecret='',$refreshToken='')
    {
        $this->clientId=$clientId;
        $this->clientSecret=$clientSecret;
        $this->refreshToken=$refreshToken;
        $this->cache=new TempCache($clientId,$clientSecret,$refreshToken);
    }

    function getAccessToken(){
        $key=$this->clientId.$this->clientSecret.$this->refreshToken;
        $token=$this->cache->get($key);

        if(!$token){
            $token=[];
            $token['refresh_token']=$this->refreshToken;
        }

        $renewAt=600;
        $token_created_at=isset($token['created_at'])?$token['created_at']:0;
        $token_expired_in=isset($token['expires_in'])?$token['expires_in']:0;
        $willBeExpireIn=$token_expired_in+$token_created_at-time();


        if(empty($token['access_token']) || $willBeExpireIn<=$renewAt ){
            $token=$this->doRefresh($this->refreshToken);

            $token['created_at']=time();
            if(!empty($token['access_token'])){
                $this->cache->put($key,$token,0);
            }

        }
        return $token['access_token'];


    }

    function doRefresh($token){
        if(is_array($token)){
            $token=$token['refresh_token'];
        }
        $postKey = (version_compare(ClientInterface::VERSION, '6') === 1) ? 'form_params' : 'body';

        $response = $this->getHttpClient()->post($this->tokenEndpoint, [
            'headers' => ['Accept' => 'application/json'],
            $postKey => [
                'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
                'grant_type'=>'refresh_token',
                'refresh_token' => $token,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }
    /**
     * Get a instance of the Guzzle HTTP client.
     *
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClient()
    {
        if (is_null($this->httpClient)) {
            $this->httpClient = new Client();
        }

        return $this->httpClient;
    }

    /**
     * @param string $clientId
     * @return TokenHelper
     */
    public function setClientId(string $clientId): TokenHelper
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * @param string $clientSecret
     * @return TokenHelper
     */
    public function setClientSecret(string $clientSecret): TokenHelper
    {
        $this->clientSecret = $clientSecret;
        return $this;
    }

    /**
     * @param mixed $accessToken
     * @return TokenHelper
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    /**
     * @param string $refreshToken
     * @return TokenHelper
     */
    public function setRefreshToken(string $refreshToken): TokenHelper
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    /**
     * @param mixed $httpClient
     * @return TokenHelper
     */
    public function setHttpClient($httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * @param $cache
     * @return TokenHelper
     */
    public function setCache($cache): TokenHelper
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @param string $tokenEndpoint
     * @return TokenHelper
     */
    public function setTokenEndpoint(string $tokenEndpoint): TokenHelper
    {
        $this->tokenEndpoint = $tokenEndpoint;
        return $this;
    }
}
