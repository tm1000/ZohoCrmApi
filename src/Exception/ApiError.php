<?php

namespace Webleit\ZohoCrmApi\Exception;

use Psr\Http\Message\ResponseInterface;

/**
 * Class ApiError
 * @package Webleit\ZohoSignApi\Exception
 */
class ApiError extends \Exception
{
    /** @var ResponseInterface */
    protected $response;

    // Error Codes
    protected const INVALID_MODULE = 'INVALID_MODULE';
    protected const INVALID_DATA = 'INVALID_DATA';
    protected const MANDATORY_NOT_FOUND = 'MANDATORY_NOT_FOUND';
    protected const INVALID_URL_PATTERN = 'INVALID_URL_PATTERN';
    protected const OAUTH_SCOPE_MISMATCH = 'OAUTH_SCOPE_MISMATCH';
    protected const NO_PERMISSION = 'NO_PERMISSION';
    protected const INTERNAL_ERROR = 'INTERNAL_ERROR';
    protected const INVALID_REQUEST_METHOD = 'INVALID_REQUEST_METHOD';
    protected const AUTHORIZATION_FAILED = 'AUTHORIZATION_FAILED';
    protected const DUPLICATE_DATA = 'DUPLICATE_DATA';
    protected const LIMIT_EXCEEDED = 'LIMIT_EXCEEDED';
    protected const TOO_MANY_REQUESTS = 'TOO_MANY_REQUESTS';

    protected $details = [];

    public function __construct(ResponseInterface $response, array $details = [])
    {
        parent::__construct($response->getReasonPhrase(), $response->getStatusCode());

        $this->response = $response;
        $this->details = $details;
    }

    public function details(): array
    {
        return $this->details;
    }

    public function response(): ResponseInterface
    {
        return $this->response;
    }

    public static function throwFromResponse(ResponseInterface $response): void
    {
        [
            'code' => $code,
            'details' => $details,
            'error' => $error,
            'message' => $message
        ] = self::getErrorCodeAndDetailsFromResponse($response);


        if (! $error) {
            return;
        }

        if (! $code) {
            switch ($response->getStatusCode()) {
                case 202:
                    throw new InvalidData($response, $details);
                case 403:
                    throw new Unauthorized($response, $details);
                case 404:
                    throw new InvalidUrlPattern($response, $details);
                case 401:
                    throw new OAuthScopeMismatch($response, $details);
                case 429:
                    throw new TooManyRequests($response, $details);
                case 405:
                    throw new MethodNotAllowed($response, $details);
                case 413:
                    throw new RequestEntityTooLarge($response, $details);
                case 415:
                    throw new UnsupportedMediaType($response, $details);
                case 400:
                case 500:
                    throw new ApiError($response, $details);
            }
        }

        switch ($code) {
            case self::INVALID_MODULE:
                throw new InvalidModule($response, $details);
            case self::INVALID_DATA:
                switch ($response->getStatusCode()) {
                    case 202:
                        throw new InvalidDataType($response, $details);
                    case 400:
                    default:
                        throw new InvalidDataFormat($response, $details);
                }
                // no break
            case self::MANDATORY_NOT_FOUND:
                throw new MandatoryDataNotFound($response, $details);
            case self::INVALID_URL_PATTERN:
                throw new InvalidUrlPattern($response, $details);
            case self::OAUTH_SCOPE_MISMATCH:
                throw new OAuthScopeMismatch($response, $details);
            case self::NO_PERMISSION:
                throw new Unauthorized($response, $details);
            case self::INTERNAL_ERROR:
                throw new ApiError($response, $details);
            case self::AUTHORIZATION_FAILED:
                throw new AuthFailed($response, $details);
            case self::DUPLICATE_DATA:
                throw new DuplicateData($response, $details);
            case self::LIMIT_EXCEEDED:
                throw new LimitExceeded($response, $details);
        }
    }

    protected static function getErrorCodeAndDetailsFromResponse(ResponseInterface $response): array
    {
        try {
            $body = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            return [
                'code' => null,
                'details' => [],
                'error' => false,
                'message' => '',

            ];
        }

        if (! isset($body['data'])) {
            return [
                'code' => $body['code'] ?? null,
                'details' => $body['details'] ?? [],
                'error' => (($body['status'] ?? '') === 'error'),
                'message' => $body['code'] ?? '',
            ];
        }

        $body = $body['data'];
        if (isset($body['code'])) {
            return [
                'code' => $body['code'] ?? null,
                'details' => $body['details'] ?? [],
                'error' => (($body['status'] ?? '') === 'error'),
                'message' => $body['code'] ?? '',
            ];
        }

        $body = collect($body);

        return [
            'code' => $body->pluck('code')->first(),
            'details' => $body->pluck('details')->flatten()->toArray(),
            'error' => ($body->pluck('status')->first() === 'error'),
            'message' => $body->pluck('message')->first() ?: '',
        ];
    }
}
