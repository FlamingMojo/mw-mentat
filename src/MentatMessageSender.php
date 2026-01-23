<?php

namespace MediaWiki\Extension\Mentat;

use Exception;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Http\HttpRequestFactory;
use Wikimedia\Http\MultiHttpClient;

class MentatMessageSender {
	public MultiHttpClient $multiHttpClient;

	public function __construct( HttpRequestFactory $httpRequestFactory ) {
		$this->multiHttpClient = $httpRequestFactory->createMultiClient( [
			'connTimeout' => 10,
			'reqTimeout' => 10
		] );
	}

	/**
	 * Sends a message to Mentat, based on the extension's configuration.
	 * @param string $hookName
	 * @param array $userInfo
	 * @param array $pageInfo
	 * @return void
	 */
	public function sendToMentat( string $hookName, array $userInfo, array $pageInfo ) {
		global $wgMentatWebhookURL;

		if ( !$wgMentatWebhookURL ) {
			return;
		}

		$urls = [];

		if ( is_array( $wgMentatWebhookURL ) ) {
			$urls = array_merge( $urls, $wgMentatWebhookURL );
		} elseif ( is_string( $wgMentatWebhookURL ) ) {
			$urls[] = $wgMentatWebhookURL;
		} else {
			wfDebugLog( 'mentat',
				'The value of $wgMentatWebhookURL is not valid and therefore no webhooks could be sent.' );
			return;
		}

        $body = $this->buildRequest( $url, $hookName, $userInfo, $pageInfo );
        $this->sendWebhook($urls, $body);
	}

	/**
	 * Builds a request for use with MultiHttpClient
	 * @param string $url
	 * @param string $hook
	 * @param string $msg
	 * @param array $userInfo
	 * @return array
	 */
	private function buildRequest( string $url, string $hook, array $userInfo, array $pageInfo ): array {
	    global $wgServer;

		return [
            'wiki' => $wgServer,
            'user' => $userInfo,
            'page' => $pageInfo,
            'hook' => $hook
        ];
	}

    /**
	 * Handles sending a webhook to Discord using cURL
	 */
	public static function sendWebhook($urls, $body) {
		DeferredUpdates::addCallableUpdate( function() use ( $urls, $body ) {
			$user_agent = 'mw-mentat/1.0 (github.com/FlamingMojo)';
			$json = json_encode($body);
            $c_handlers = [];
            $result = [];
            $mh = curl_multi_init();

            foreach ($urls as &$value) {
                $c_handlers[$value] = curl_init( $value );
                curl_setopt( $c_handlers[$value], CURLOPT_POST, 1 ); // Send as a POST request
                curl_setopt( $c_handlers[$value], CURLOPT_POSTFIELDS, $json ); // Send the JSON in the POST request
                curl_setopt( $c_handlers[$value], CURLOPT_FOLLOWLOCATION, 1 );
                curl_setopt( $c_handlers[$value], CURLOPT_HEADER, 0 );
                curl_setopt( $c_handlers[$value], CURLOPT_RETURNTRANSFER, 1 );
                curl_setopt( $c_handlers[$value], CURLOPT_CONNECTTIMEOUT, 10 ); // Add a timeout for connecting to the site
                curl_setopt( $c_handlers[$value], CURLOPT_TIMEOUT, 10 ); // Do not allow cURL to run for a long time
                curl_setopt( $c_handlers[$value], CURLOPT_USERAGENT, $user_agent ); // Add a unique user agent
                curl_setopt( $c_handlers[$value], CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json'
                ));
                curl_multi_add_handle( $mh, $c_handlers[$value] );
            }

            $running = null;
            do {
                curl_multi_exec($mh, $running);
            } while ($running);

            // Remove all handlers and then close the multi handler
            foreach($c_handlers as $k => $ch) {
                $result[$k] = curl_multi_getcontent($ch);
                wfDebugLog( 'mentat', 'Result of cURL was: ' . $result[$k] );
                curl_multi_remove_handle($mh, $ch);
            }

            curl_multi_close($mh);
        });

		return true;
	}
}
