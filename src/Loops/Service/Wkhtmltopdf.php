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

use Loops;
use Loops\ArrayObject;
use Loops\Service;
use mikehaertl\wkhtmlto\Pdf;

/**
 * A service that assists in creating PDF files
 *
 * It looks for the following libraries that are installed via composer and uses the first one found.
 * - Michal Haertls wkhtmltopdf wrapper: see method getMikehaertlWkhtmltoPdf for details
 */
class Wkhtmltopdf extends Service {
    /**
     * Create and return Michal Haertls wkhtmltopdf wrapper class as the pdf service.
     * 
     * Values from the config will be passed as options.
     * Value 'enableXvfb' can be passed at config top level and is used correctly.
     * (No need to create the 'commandOptions' section)
     *
     * <code>
     *     //inside a method of Loops\Object
     *     $this->pdf->addPage("http://www.example.com");
     *     $this->pdf->send();
     *     exit;
     * </code>
     *
     * It is assumed that wkhtmltopdf is available and working in your environment.
     *
     * A quick way to setup wkhtmltopdf (with minimal dependencies):
     * 
     * 1. Install the 'h4cc/wkhtmltopdf-amd64' package from composer
     *    - 'composer require h4cc/wkhtmltopdf-amd64'
     * 2. Install Xvfb + X Rendering Extension
     *    - Debian/Ubuntu: 'apt-get install xvfb libxrender1'
     * 3. Add the following to your config file:
     * <code>
     *     [pdf]
     *     binary                  = wkhtmltopdf-amd64
     *     enableXvfb              = true
     * </code>
     *
     * @return mikehaertl\wkhtmlto\Pdf Michal Haertls wkhtmltopdf wrapper class
     */
    public static function getService(ArrayObject $config, Loops $loops) {
        $options = array();
        
        foreach($config as $key => $value) {
            switch($key) {
                case 'enableXvfb': $options['commandOptions'][$key] = (bool)$value; break;
                default: $options[$key] = $value;
            }
        }
        
        return new Pdf($options);
    }
}