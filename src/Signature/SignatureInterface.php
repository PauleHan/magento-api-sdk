<?php
namespace Triggmine\Signature;

use Triggmine\Credentials\CredentialsInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Interface used to provide interchangeable strategies for signing requests
 * using the various TriggMIne signature protocols.
 */
interface SignatureInterface
{
    /**
     * Signs the specified request with an TriggMine signing protocol by using
     * the
     * provided TriggMine account credentials and adding the required headers to
     * the
     * request.
     *
     * @param RequestInterface     $request     Request to sign
     * @param CredentialsInterface $credentials Signing credentials
     *
     * @return RequestInterface Returns the modified request.
     */
    public function signRequest(
        RequestInterface $request,
        CredentialsInterface $credentials
    );

    /**
     * Create a pre-signed request.
     *
     * @param RequestInterface     $request     Request to sign
     * @param CredentialsInterface $credentials Credentials used to sign
     * @param int|string|\DateTime $expires The time at which the URL should
     *     expire. This can be a Unix timestamp, a PHP DateTime object, or a
     *     string that can be evaluated by strtotime.
     *
     * @return RequestInterface
     */
    public function presign(
        RequestInterface $request,
        CredentialsInterface $credentials,
        $expires
    );
}