<?php
namespace Triggmine\Commerce;

use Triggmine\TriggmineClient;

/**
 * Auto Scaling client.
 *
 * @method \Triggmine\Result onAddProductInCart(array $args = [])
 * @method \GuzzleHttp\Promise\Promise onAddProductInCartAsync(array $args = [])
 * @method \Triggmine\Result onFullCartChange(array $args = [])
 * @method \GuzzleHttp\Promise\Promise onFullCartChangeAsync(array $args = [])
 * @method \Triggmine\Result onConvertCartToOrder(array $args = [])
 * @method \GuzzleHttp\Promise\Promise onConvertCartToOrderAsync(array $args = [])
 * @method \Triggmine\Result onLogin(array $args = [])
 * @method \GuzzleHttp\Promise\Promise onLoginAsync(array $args = [])
 * @method \Triggmine\Result onLogout(array $args = [])
 * @method GuzzleHttp\Promise\Promise onLogoutAsync(array $args = [])
 * @method \Triggmine\Result onCustomerRegister(array $args = [])
 * @method GuzzleHttp\Promise\Promise onCustomerRegisterAsync(array $args = [])
 */
class CommerceClient extends TriggmineClient{}
