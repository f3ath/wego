<?php
namespace F3\Wego;
use DateTime;
use PHPCurl\CurlHttp\HttpClient;
/**
 * WAN.travel API client
 * @see http://support.wan.travel/hc/en-us
 */
class WegoApiClient
{
    const DATE_FORMAT = 'Ymd';
    /**
     * @var HttpClient
     */
    private $http;
    /**
     * @var string
     */
    private $apiUrl;
    /**
     * @var string
     */
    private $key;
    /**
     * @var string
     */
    private $tsCode;
    /**
     * Client constructor.
     * @param string $key
     * @param string $tsCode
     * @param string $apiUrl
     * @param HttpClient $http
     */
    public function __construct($key, $tsCode, $apiUrl = 'http://api.wego.com', HttpClient $http = null)
    {
        $this->key = $key;
        $this->tsCode = $tsCode;
        $this->apiUrl = $apiUrl;
        $this->http = $http ?: new HttpClient();
    }
    /**
     * Start a new hotel search
     *
     * @see http://support.wan.travel/hc/en-us/articles/200713154-Wego-Hotels-API#api_search_new
     *
     * @param string $location The location ID of the location to search for.
     * @param DateTime $checkIn Check-in date
     * @param DateTime $checkOut Check-out date
     * @param int $rooms Number of hotel rooms required. Defaults to 1
     * @param int $guests Number of guests staying. Defaults to 2
     * @param string $ip The IP address of the end user who is performing the search (not your backend server). We require this to display room rates that are valid for the user's country.
     * @param string $country Country code of the user. We require this to display room rates that are valid for the user's country.
     *
     * @return string Search ID
     */
    public function startSearch(
        $location,
        DateTime $checkIn,
        DateTime $checkOut,
        $rooms = 1,
        $guests = 2,
        $ip = 'direct',
        $country = 'US'
    ) {
        $response = $this->call('/hotels/api/search/new', [
            'location_id' => $location,
            'check_in' => $checkIn->format(self::DATE_FORMAT),
            'check_out' => $checkOut->format(self::DATE_FORMAT),
            'user_ip' => $ip,
            'country_code_for_site' => $country,
            'rooms' => (int)$rooms,
            'quests' => (int)$guests,
        ]);
        return $response["search_id"];
    }
    /**
     * Search for a Wego Hotels location
     *
     * @see http://support.wan.travel/hc/en-us/articles/200713154-Wego-Hotels-API#api_locations_search
     *
     * @param $query
     *
     * @param string $lang Language of results
     * @param int $page Page of results to return. Use this together with per_page
     * @param int $perPage Number of results to return per page. Use this together with page. Defaults to 10
     * @return mixed
     */
    public function getLocations($query, $lang = 'en', $page = 1, $perPage = 10)
    {
        $query = preg_replace('/[^a-z0-9]/i', ' ', $query);
        $query = implode('_', preg_split('/ /', $query, -1, PREG_SPLIT_NO_EMPTY));
        $query = strtolower($query);
        return $this->call('/hotels/api/locations/search', [
            'q' => $query,
            'lang' => $lang,
            'page' => (int)$page,
            'per_page' => (int)$perPage,
        ]);
    }
    /**
     * Get results of a search
     *
     * @see http://support.wan.travel/hc/en-us/articles/200713154-Wego-Hotels-API#api_search_search_id
     *
     * @param string $id ID of search for retrieving "live" prices together with the hotel
     * @param bool $refresh Whether to refresh the results with any new results since the last request.
     * @param string $currency Currency to display prices in - use ISO 4217 3-letter currency codes. Defaults to USD
     * @param string $sort popularity|name|price|satisfaction|stars
     * @param string $order asc|desc
     * @param string $popularWith 2-character country code
     * @param int $page Page of results to return
     * @param int $perPage Number of results to return per page
     *
     * @return mixed
     */
    public function getSearchResults(
        $id,
        $refresh = false,
        $currency = 'USD',
        $sort = 'popularity',
        $order = 'asc',
        $popularWith = 'XX',
        $page = 1,
        $perPage = 20
    ) {
        return $this->call('/hotels/api/search/' . urlencode($id), [
            'refresh' => $refresh,
            'currency_code' => $currency,
            'sort' => $sort ? 'true' : 'false',
            'order' => $order,
            'popular_with' => $popularWith,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }
    /**
     * Get details of a hotel (live search)
     *
     * @see http://support.wan.travel/hc/en-us/articles/200713154-Wego-Hotels-API#api_show_hotel_id
     *
     * @param string $searchID ID of search for retrieving "live" prices together with the hotel.
     * @param string $hotelID ID of the hotel.
     * @param string $currency Currency to display prices in - use ISO 4217 3-letter currency codes. Defaults to USD.
     * @param string $lang Language of results. Defaults to en.
     *
     * @return mixed
     */
    public function getDetails($searchID, $hotelID, $currency = 'USD', $lang = 'en')
    {
        return $this->call('/hotels/api/search/show', [
            'search_id' => $searchID,
            'hotel_id' => $hotelID,
            'currency' => $currency,
            'lang' => $lang,
        ]);
    }
    /**
     * Do HTTP GET
     * @param string $uri
     * @param array $query
     * @return mixed Parsed JSON response
     */
    public function call($uri, array $query)
    {
        $query['key'] = $this->key;
        $query['ts_code'] = $this->tsCode;
        $fullUrl = $this->apiUrl . $uri . '?' . http_build_query($query);
        $response = $this->http->get($fullUrl);
        $json = json_decode($response->getBody(), true);
        if ($response->getCode() === 200) {
            return $json;
        }
        throw new WegoApiException(isset($json['error']) ? $json['error'] : 'Unknown error', $response->getCode());
    }
}