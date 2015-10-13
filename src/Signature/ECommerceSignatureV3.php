<?php
namespace Triggmine\Signature;

use Triggmine\Credentials\CredentialsInterface;
use Psr\Http\Message\RequestInterface;


/**
 * TriggMine ECommerce signature version 4 support.
 */
class ECommerceSignatureV3 extends SignatureV3
{
    /**
     * Always add a x-tm-content-sha-256 for data integrity.
     */
    public function signRequest(
        RequestInterface $request,
        CredentialsInterface $credentials
    ) {
        if (!$request->hasHeader('x-tm-content-sha256')) {
            $request = $request->withHeader(
                'X-Tm-Content-Sha256',
                $this->getPayload($request)
            );
        }
        return parent::signRequest($request, $credentials);
    }

    /**
     * Override used to allow pre-signed URLs to be created for an
     * in-determinate request payload.
     */
    protected function getPresignedPayload(RequestInterface $request)
    {
        return 'UNSIGNED-PAYLOAD';
    }

    /**
     * TriggMine ECommerce does not double-encode the path component in the canonical request
     */
    protected function createCanonicalizedPath($path)
    {
        return '/' . ltrim($path, '/');
    }
}