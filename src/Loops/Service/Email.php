<?php
/**
 * This file is part of the Loops framework.
 *
 * @author Lukas <lukas@loopsframework.com>
 * @license https://raw.githubusercontent.com/loopsframework/base/master/LICENSE
 * @link https://github.com/loopsframework/base
 * @link https://loopsframework.com/
 * @package extra
 * @version 0.1
 */

namespace Loops\Service;

class Email extends AliasedService {
    protected static $aliases = [ 'swiftmailer'/* add other later*/];
}