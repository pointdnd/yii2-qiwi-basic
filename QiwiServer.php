<?php
/**
 * @copyright Ribris
 * @author Sergey Korshunov <sergey@korshunov.pro>
 * @version 0.1b
 */

namespace ribris\qiwi;

use Yii;
use yii\web\Response;
use yii\web\XmlResponseFormatter;


/**
 * QIWI payment notify server
 */
class QiwiServer
{
    /**
     * @var array
     */
    private static $_notifyFields = [
        'bill_id',
        'status',
        'error',
        'amount',
        'user',
        'ccy',
        'comment'
    ];

    /**
     * @return array|bool
     */
    public static function request()
    {
        preg_match('#Basic\s([^;]+)#m', implode(';', $_SERVER), $basicToken);

        $token = base64_encode(
            Yii::$app->params['QIWI_SHOP_ID'] . ':' . Yii::$app->params['QIWI_NOTIFICATION_PWD']
        );

        if ($token === $basicToken[1]) {
            $result = [];
            foreach ($_POST as $k => $v) {
                if(in_array($k, self::$_notifyFields)) {
                    $result[$k] = $v;
                }
            }
            return $result;
        }
        return false;
    }


    /**
     * @param integer $returnCode
     */
    public static function response($returnCode = 0)
    {
        $response = new Response;
        $response->data = [$returnCode];
        $response->format = $response::FORMAT_XML;

        $response->formatters[$response::FORMAT_XML] = new XmlResponseFormatter;
        $response->formatters[$response::FORMAT_XML]->rootTag = 'result';
        $response->formatters[$response::FORMAT_XML]->itemTag = 'result_code';
        $response->formatters[$response::FORMAT_XML]->contentType = 'text/xml';

        $response->send();
        die;
    }

}