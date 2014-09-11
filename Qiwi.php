<?php
/**
 * @copyright Ribris
 * @author Sergey Korshunov <sergey@korshunov.pro>
 * @version 0.1b
 */

namespace ribris\qiwi;

use Yii;

/**
 * QIWI payment REST wrapper
 */
class Qiwi
{
    /**
     * QIWI REST API url
     */
    const API_URL = 'https://w.qiwi.com/api/v2/prv';

    /**
     * QIWI REST bills action
     */
    const API_ACTION = 'bills';

    /**
     * @var int
     */
    public static $error = 0;

    /**
     * @var array
     */
    public static $result = [];

    /**
     * List of bill status
     * @var array
     */
    private static $BILL_STATUS = [
        'waiting'   => 'Счет выставлен, ожидает оплаты Нет',
        'paid'      => 'Счет оплачен Да',
        'rejected'  => 'Счет отклонен Да',
        'unpaid'    => 'Ошибка при проведении оплаты. Счет не оплачен Да',
        'expired'   => 'Время жизни счета истекло. Счет не оплачен',
    ];

    /**
     * List f refund bill status
     * @var array
     */
    private static $REFUND_STATUS = [
        'processing'=> 'Платеж в проведении',
        'success'   => 'Платеж проведен',
        'fail'      => 'Платеж неуспешен',
    ];

    /**
     * List of error codes
     * @var array
     */
    private static $ERROR_CODES = [
        '0'    => ' Успех',
        '5'    => ' Неверныенные в параметрах запроса',
        '13'   => ' Сервер занят, повторите запрос позже',
        '78'   => ' Недопустимая операция',
        '150'  => ' Ошибка авторизации',
        '152'  => ' Не подключен или отключен протокол',
        '210'  => ' Счет не найден',
        '215'  => ' Счет с таким bill_id уже существует',
        '241'  => ' Сумма слишком мала',
        '242'  => ' Сумма слишком велика',
        '298'  => ' Кошелек с таким номером не зарегистрирован',
        '300'  => ' Техническая ошибка',
        '303'  => ' Неверный номер телефона',
        '316'  => ' Попытка авторизации заблокированным провайдером',
        '319'  => ' прав нанную операцию',
        '341'  => ' Обязательный параметр указан неверно или отсутствует в запросе',
        '1001' => ' Запрещенная валюта для провайдера',
        '1003' => ' Не удалось получить курс конвертации длянной пары валют',
        '1019' => ' Не удалось определить сотового оператора для мобильной коммерции',
    ];

    /**
     * List of notify codes
     * @var array
     */
    private static $NOTIFY_CODES = [
        '0'    => 'Успех',
        '5'    => 'Ошибка формата параметров запроса',
        '13'   => 'Ошибка соединения с базой данных',
        '150'  => 'Ошибка проверки пароля',
        '151'  => 'Ошибка проверки подписи',
        '300'  => 'Ошибка связи с сервером',
    ];

    /**
     * @param string $bill_id
     * @param string $user tel:+71234567890 (^tel:\+\d{1,15}$)
     * @param string $amount 1.00 (^\d+(.\d{0,3})?$)
     * @param string $ccy RUB etc (^[a-zA-Z]{3}$)
     * @param string $comment 255 char (^\.{0,255}$)
     * @param string $lifetime datetime ISO 8601 def 45 (^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$)
     * @param string $pay_source 'mobile' or 'qw' (^((mobile)|(qw)){1}$)
     * @param string $prv_name provider name (^\.{1,100}$)
     * @return array
     */
    public static function createBill($bill_id, $user, $amount, $ccy, $comment,
                                      $lifetime = null, $pay_source = 'qw', $prv_name = '')
    {
        $params = [
            'bill_id' => $bill_id,
            'user'    => 'tel:' . $user,
            'amount'  => $amount,
            'ccy'     => $ccy,
            'comment' => $comment,
            'lifetime'=> (!$lifetime) ? date('c', (time()+3880000)) : $lifetime,
        ];

        if($pay_source) {
            $params['pay_source'] = $pay_source;
        }

        if($prv_name) {
            $params['prv_name'] = $prv_name;
        }

        return self::restQuery($bill_id, $params, 'PUT');
    }

    /**
     * @param string $bill_id
     * @return array
     */
    public static function checkBill($bill_id)
    {
        return self::restQuery($bill_id);
    }

    /**
     * @param string $bill_id
     * @return array
     */
    public static function cancelBill($bill_id)
    {
        return self::restQuery($bill_id, ['status' => 'rejected'], 'PATCH');
    }

    /**
     * @param string $bill_id
     * @param integer $amount  1.00 (^\d+(.\d{0,3})?$)
     * @param integer $refund_id rand(1, 999999999)
     * @return array
     */
    public static function refundBill($bill_id, $amount, $refund_id)
    {
        return self::restQuery($bill_id, ['amount' => $amount], 'PUT',
            ['refund', $refund_id]
        );
    }

    /**
     * @param string $bill_id
     * @param integer $refund_id
     * @return array
     */
    public static function checkRefundBill($bill_id, $refund_id)
    {
        return self::restQuery($bill_id, [], 'PUT',
            ['refund', $refund_id]
        );
    }

    /**
     * @param $bill_id
     * @param array $params
     * @param string $method
     * @param array $action
     * @return array
     */
    private static function restQuery($bill_id, $params = [], $method = 'GET', $action = [])
    {

        $api_url = [
            self::API_URL,
            Yii::$app->params['QIWI_SHOP_ID'],
            self::API_ACTION,
            $bill_id
        ];

        if(!empty($action) && is_array($action)) {
            $api_url = array_merge($api_url, $action);
        }

        $ch = curl_init(implode('/', $api_url));

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, Yii::$app->params['QIWI_REST_ID'] . ":" . Yii::$app->params['QIWI_REST_PWD']);

        curl_setopt($ch,CURLOPT_HTTPHEADER,array (
            "Accept: application/json"
        ));

        $res = curl_exec ($ch);
        $err = curl_error($ch);
        curl_close ($ch);

        if($res) {
            $res = json_decode($res);
        }

        return [
            'result'=>$res,
            'query_error'=>$err
        ];
    }

}