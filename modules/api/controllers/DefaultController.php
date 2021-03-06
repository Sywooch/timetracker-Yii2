<?php

namespace app\modules\api\controllers;

use app\helpers\WorkTime;
use app\models\TrackerVersion;
use app\models\User;
use app\models\WorkLog;
use Yii;
use yii\base\ErrorException;
use yii\filters\ContentNegotiator;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;

/**
 * Default controller for the `api` module
 */
class DefaultController extends Controller
{
    public function behaviors()
    {
        Yii::$app->controller->enableCsrfValidation = false;

        $behaviors = parent::behaviors();
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::className(),
            'formats' => [
                'application/json' => Response::FORMAT_JSON
            ]

        ];

        return $behaviors;
    }

    /**
     * @param $worklog
     * @return bool|int
     */
    protected function setLog($worklog)
    {
        $user = User::findByEmail($worklog['email']);

        if ($user) {
            $log = new WorkLog();

            $time = round($worklog['dateTime'] / 1000);

            $log->user_id = $user->id;
            $log->screenshot = $worklog['screenshot'];
            $log->timestamp = $time;
            $log->countMouseEvent = $worklog['countMouseEvent'];
            $log->countKeyboardEvent = $worklog['countKeyboardEvent'];
            $log->activityIndex = $worklog['activityIndex'];
            $log->issueKey = $worklog['issueKey'];
            $log->workTime = WorkTime::check($time);
            $log->dateTime = date('Y-m-d H:i:s', $time);

            if ($log->save())
                return $log->id;
        }

        return false;
    }

    /**
     * Renders the index view for the module
     * @return string
     */
    public function actionIndex()
    {
        return true;
    }

    /**
     * @return array|mixed|null|string|\yii\db\ActiveRecord
     * @throws BadRequestHttpException
     */
    public function actionLatestVersion()
    {
        try {
            $request = Yii::$app->request;

            if ($request->isGet) {
                return TrackerVersion::find()->select(['version', "DATE_FORMAT(`date`, '%Y-%m-%d') as date"])->orderBy(['tracker_version.date' => SORT_DESC])->one();
            }

            if ($request->isPost) {
                if ($request->post('newVersion')) {
                    $version = new TrackerVersion();
                    $version->version = $request->post('newVersion');
                    $version->save();

                    return $version->version;
                } else {
                    throw new BadRequestHttpException('Bad POST data!');
                }
            }

            throw new BadRequestHttpException('This request type not supported!');
        } catch (ErrorException $e) {
            throw new BadRequestHttpException('Global request error!');
        }
    }

    /**
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionLog()
    {
        try {
            $request = Yii::$app->request;

            if ($request->isPost) {
                $body = Yii::$app->getRequest()->getBodyParams();

                $hash = md5($body['workLog']['dateTime']);

                if ($body['auth'] === $hash) {
                    $log_id = $this->setLog($body['workLog']);
                    \Yii::$app->response->format = Response::FORMAT_JSON;
                    $response = [];
                    if($log_id)
                        $response['status'] = 200;
                    else
                        $response['status'] = 500;
                    $response['message']['id'] = $log_id;
                    return $response;
                } else {
                    throw new BadRequestHttpException('Authorization check failed!');
                }
            } else {
                throw new BadRequestHttpException('This request type not supported!');
            }
        } catch (ErrorException $e) {
            throw new BadRequestHttpException('Global request error!');
        }
    }

    /**
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionLogs()
    {
        try {
            $request = Yii::$app->request;

            if ($request->isPost) {
                $body = Yii::$app->getRequest()->getBodyParams();

                $hash = md5($body['workLogs'][0]['dateTime']);

                if ($body['auth'] === $hash) {
                    foreach ($body['workLogs'] as $log) {
                        $this->setLog($log);
                    }

                    \Yii::$app->response->format = Response::FORMAT_JSON;
                    return ['message'=>true];
                } else {
                    throw new BadRequestHttpException('Authorization check failed!');
                }
            } else {
                throw new BadRequestHttpException('This request type not supported!');
            }
        } catch (ErrorException $e) {
            throw new BadRequestHttpException('Global request error!');
        }
    }
}
