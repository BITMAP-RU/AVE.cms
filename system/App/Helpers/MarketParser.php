<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Helpers/MarketParser.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Helpers;

	use App\Helpers\Json;
	use App\Helpers\Debug;

	/**
	 * MarketParser API Helper Class
	 *
	 * This class encapsulates all functionality for interacting with the MarketParser API
	 */
	class MarketParser
	{
		/**
		 * @var string The API key for authentication
		 */
		private $apiKey;

		/**
		 * @var string The campaign ID to work with
		 */
		private $campaignId;

		/**
		 * @var string Base URL for the MarketParser API
		 */
		private $baseUrl = 'https://cp.marketparser.ru/api/v2';

		/**
		 * Constructor
		 *
		 * @param string $apiKey The API key for authentication
		 * @param string $campaignId The campaign ID to work with
		 */
		public function __construct($apiKey, $campaignId)
		{
			$this->apiKey = $apiKey;
			$this->campaignId = $campaignId;
		}

		/**
		 * Get list of campaigns
		 *
		 * @return array|false Campaign data or false on failure
		 */
		public function getCampaigns()
		{
			Debug::print('Список кампаний');

			$url = $this->baseUrl . '/campaigns.json';

			return $this->makeApiCall($url);
		}

		/**
		 * Get price information for a campaign
		 *
		 * @return array|false Price data or false on failure
		 */
		public function getPriceInfo()
		{
			Debug::print('Получение информации о прайсе кампании');

			$url = sprintf('%s/campaigns/%s/price.json', $this->baseUrl, $this->campaignId);

			return $this->makeApiCall($url);
		}

		/**
		 * Create a report for a campaign
		 *
		 * @return array|false Report data or false on failure
		 */
		public function createReport()
		{
			Debug::print('Создание отчёта по кампании');

			$url = sprintf('%s/campaigns/%s/reports.json', $this->baseUrl, $this->campaignId);

			return $this->makeApiCall($url, 'POST');
		}

		/**
		 * Get report information
		 *
		 * @param string $reportId The ID of the report to get information for
		 * @return array|false Report data or false on failure
		 */
		public function getReportInfo($reportId)
		{
			Debug::print('Получение информации об отчёте');

			$url = sprintf('%s/campaigns/%s/reports/%s.json', $this->baseUrl, $this->campaignId, $reportId);

			return $this->makeApiCall($url);
		}

		/**
		 * Get parsing results from a report
		 *
		 * @param string $reportId The ID of the report to get results for
		 * @param int $perPage Number of results per page (default: 10)
		 * @return array|false Report results or false on failure
		 */
		public function getReportResults($reportId, $perPage = 10)
		{
			Debug::print('Получение результатов парсинга отчёта');

			Debug::startTime('Price');

			$url = sprintf('%s/campaigns/%s/reports/%s/results.json?per_page=%d',
						  $this->baseUrl, $this->campaignId, $reportId, $perPage);

			$result = $this->makeApiCall($url);

			$time = Debug::endTime('Price');
			Debug::echo($time);

			return $result;
		}

		/**
		 * Get list of reports for a campaign
		 *
		 * @return array|false Report list or false on failure
		 */
		public function getReportsList()
		{
			Debug::print('Получение списка отчётов кампании');

			$url = sprintf('%s/campaigns/%s/reports.json', $this->baseUrl, $this->campaignId);

			return $this->makeApiCall($url);
		}

		/**
		 * Make an API call to MarketParser
		 *
		 * @param string $url The URL to make the request to
		 * @param string $method HTTP method (GET or POST)
		 * @return array|false Response data or false on failure
		 */
		private function makeApiCall($url, $method = 'GET')
		{
			$curlHandle = curl_init($url);

			$headers = [
				'Api-Key: ' . $this->apiKey,
				'Content-Type: application/json',
			];

			$options = [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => $headers,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_CAINFO => BASEPATH . DS . '/cert/cacert.pem',
			];

			if ($method === 'POST') {
				$options[CURLOPT_POST] = true;
			}

			curl_setopt_array($curlHandle, $options);

			$response = curl_exec($curlHandle);
			$curlError = curl_error($curlHandle);
			curl_close($curlHandle);

			if ($response === false) {
				//curl error occurred, check $curlError for details
				return false;
			} else {
				$parsedResponse = Json::decode($response, true);
				Debug::echo($parsedResponse);
				return $parsedResponse;
			}
		}
	}
