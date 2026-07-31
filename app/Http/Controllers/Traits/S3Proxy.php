<?php

namespace App\Http\Controllers\Traits;

use Log;

trait S3Proxy {

	/**
	 * Generate an AWS S3 presigned URL using Signature Version 4 (pure PHP, no SDK required).
	 */
	protected function generateS3PresignedUrl($bucket, $key, $region, $accessKey, $secretKey, $expiresIn = 3600) {
		$host      = "{$bucket}.s3.{$region}.amazonaws.com";
		$datetime  = gmdate('Ymd\THis\Z');
		$date      = substr($datetime, 0, 8);

		$credentialScope = "{$date}/{$region}/s3/aws4_request";
		$credential      = "{$accessKey}/{$credentialScope}";

		// URI-encode each path segment individually
		$encodedPath = implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
		$canonicalUri = '/' . $encodedPath;

		// Canonical query string (sorted, fully URI-encoded, signature excluded)
		$queryParams = [
			'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
			'X-Amz-Credential'    => $credential,
			'X-Amz-Date'          => $datetime,
			'X-Amz-Expires'       => (string) $expiresIn,
			'X-Amz-SignedHeaders' => 'host',
		];
		ksort($queryParams);
		$canonicalQueryParts = [];
		foreach ($queryParams as $k => $v) {
			$canonicalQueryParts[] = rawurlencode($k) . '=' . rawurlencode($v);
		}
		$canonicalQueryString = implode('&', $canonicalQueryParts);

		// Canonical request
		$canonicalRequest = implode("\n", [
			'GET',
			$canonicalUri,
			$canonicalQueryString,
			"host:{$host}\n",  // canonical headers (trailing blank line included)
			'host',            // signed headers
			'UNSIGNED-PAYLOAD',
		]);

		// String to sign
		$stringToSign = implode("\n", [
			'AWS4-HMAC-SHA256',
			$datetime,
			$credentialScope,
			hash('sha256', $canonicalRequest),
		]);

		// Derived signing key
		$signingKey = hash_hmac('sha256', 'aws4_request',
			hash_hmac('sha256', 's3',
				hash_hmac('sha256', $region,
					hash_hmac('sha256', $date, 'AWS4' . $secretKey, true),
					true
				),
				true
			),
			true
		);

		$signature = hash_hmac('sha256', $stringToSign, $signingKey);

		return "https://{$host}{$canonicalUri}?{$canonicalQueryString}&X-Amz-Signature={$signature}";
	}

	/**
	 * Fetch an object from S3 via a presigned URL and stream it back, forwarding the Range header.
	 */
	protected function streamS3Object($s3Key) {
		$bucket    = env('AWS_BUCKET', 'ccr-oncogenomics-ccdi-dev');
		$region    = env('AWS_DEFAULT_REGION', 'us-east-1');
		$accessKey = env('AWS_ACCESS_KEY_ID');
		$secretKey = env('AWS_SECRET_ACCESS_KEY');

		Log::info("S3 proxy: s3://{$bucket}/{$s3Key}");

		$presignedUrl = $this->generateS3PresignedUrl($bucket, $s3Key, $region, $accessKey, $secretKey, 3600);

		// Proxy through our server – IGV.js never makes a cross-origin request to S3
		$guzzleHeaders = [];
		if (isset($_SERVER['HTTP_RANGE'])) {
			$guzzleHeaders['Range'] = $_SERVER['HTTP_RANGE'];
		}

		try {
			$client = new \GuzzleHttp\Client();
			$s3Response = $client->get($presignedUrl, [
				'headers'     => $guzzleHeaders,
				'stream'      => true,
				'http_errors' => false,  // let us handle 4xx/5xx ourselves
			]);
		} catch (\Exception $e) {
			Log::error("S3 proxy error: " . $e->getMessage());
			return response('S3 error: ' . $e->getMessage(), 502);
		}

		$httpStatus = $s3Response->getStatusCode();
		Log::info("S3 response status: $httpStatus for $s3Key");

		$responseHeaders = [
			'Content-Type'  => $s3Response->getHeaderLine('Content-Type') ?: 'application/octet-stream',
			'Accept-Ranges' => 'bytes',
		];
		if ($s3Response->hasHeader('Content-Length'))
			$responseHeaders['Content-Length'] = $s3Response->getHeaderLine('Content-Length');
		if ($s3Response->hasHeader('Content-Range'))
			$responseHeaders['Content-Range'] = $s3Response->getHeaderLine('Content-Range');

		// On S3 errors, log the body for debugging and return the status
		if ($httpStatus >= 400) {
			$errorBody = (string) $s3Response->getBody();
			Log::error("S3 error response ($httpStatus): $errorBody");
			return response($errorBody, $httpStatus, ['Content-Type' => 'application/xml']);
		}

		$body = $s3Response->getBody();
		return response()->stream(function () use ($body) {
			while (!$body->eof()) {
				echo $body->read(64 * 1024);
				flush();
			}
		}, $httpStatus, $responseHeaders);
	}
}
