<?php
namespace Triggmine\Credentials;

/**
 * Provides access to the Triggmine credentials used for accessing Triggmine services: Triggmine
 * access key ID, secret access key, and security token. These credentials are
 * used to securely sign requests to Triggmine services.
 */
interface CredentialsInterface
{
    /**
     * Returns the Triggmine access key ID for this credentials object.
     *
     * @return string
     */
    public function getAccessKeyId();

    /**
     * Returns the Triggmine secret access key for this credentials object.
     *
     * @return string
     */
    public function getSecretKey();

    /**
     * Get the associated security token if available
     *
     * @return string|null
     */
    public function getSecurityToken();

    /**
     * Get the UNIX timestamp in which the credentials will expire
     *
     * @return int|null
     */
    public function getExpiration();

    /**
     * Check if the credentials are expired
     *
     * @return bool
     */
    public function isExpired();

    /**
     * Converts the credentials to an associative array.
     *
     * @return array
     */
    public function toArray();
}
