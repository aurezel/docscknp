<?php
namespace app\index\controller;

use app\index\validate\IndexValidate;
use think\facade\Env;

class Index
{
    public function index()
    {
        return 'Welcome to here.';
    }

    public function miss()
    {
        return 'Page not found from https://'.$_SERVER['HTTP_HOST'];
    }

    /**
     * Get env config
     * Date: 2021/6/11 
     * @return \think\response\Json
     * */
    
    public function getAccountInfo(){
        $envPath = Env::get('root_path')."/.env";
        if (file_exists($envPath))
        {
            $content  = parse_ini_file($envPath);
            return apiSuccess([
            'name'=>$content['name'],
            'public_key'=>$content['public_key'],
            'remote_url' => $content['remote_url']
            ],'get Config Info Success.');
        }else{
            generateApiLog('配置文件不存在');
            return apiError();
        }
        
    }

    /**
     * Update env config
     * by: hjl
     * Date: 2021/5/20 21:51
     * @return \think\response\Json
     */
    public function accountUpdate()
    {
        $encrypted_data = input('post.param');
        if (!isset($encrypted_data))
        {
            return apiError();
        }
        $iv = Env::get('stripe.encrypt_iv');
        $password = Env::get('stripe.encrypt_password');
        $result = openssl_decrypt($encrypted_data,'DES-OFB',$password,OPENSSL_DONT_ZERO_PAD_KEY,$iv);
        $resultArr = json_decode($result,true);
        if (empty($resultArr))
        {
            generateApiLog("解密失败，秘钥==>$encrypted_data");
            return apiError();
        }

        $validate = validate(IndexValidate::class);
        // 校验参数
        $checkResult = $validate->scene('accountUpdate')->check($resultArr);
        if (!$checkResult) {
            // 参数异常
            generateApiLog('Update config api exception:' .$validate->getError());
            return apiError();
        }

        $envPath = Env::get('root_path')."/.env";
        if (file_exists($envPath))
        {
            $envConfigField = [
                'notify_user_data_url',//single config
                'center' => ['remote_url'],
                'stripe' => ['name','public_key','private_key','use_proxy','proxy_server','proxy_type','proxy_auth','force_3d','stripe_version','merchant_token','checkout_success_path','checkout_cancel_path']
            ];
            $boolean_fields = ['app_debug', 'app_trace', 'local_env'];
            
            $ini_config = parse_ini_file($envPath, true, INI_SCANNER_TYPED);
            
            $envContent = file_get_contents($envPath);
            $originalLog  = $finalLog = $allConfigField = array();
            foreach ($envConfigField as $key => $value)
            {
                // single config
                if (is_numeric($key))
                {
                    if (isset($resultArr[$value]))
                    {
                        $config = env("{$value}");
                        $originalLog['original_'.$value] = $config;
                        $ini_config[$key] = $resultArr[$value];
                        $allConfigField[] = $value;
                    }

                }else{
                    foreach ($value as $field)
                    {
                        if (isset($resultArr[$field]))
                        {
                            $config = env("{$key}.{$field}");
                            $originalLog['original_'.$field] = $config;
                            $ini_config[$key][$field] = $resultArr[$field];
                            $allConfigField[] = $field;
                        }
                    }
                }
            }
            $this->write_ini_file($envPath, $ini_config, $boolean_fields);
            
            $content = parse_ini_file($envPath);
            foreach ($content as $key => $config)
            {
                if (in_array($key,$allConfigField))
                    $finalLog['env_final_'.$key] = $config;
            }
            $logArr = compact('originalLog','resultArr','finalLog');
            generateApiLog($logArr);
            return apiSuccess([],'Update Success.');
        }
        generateApiLog('Update config Exception!Posted param is:' . $encrypted_data);
        return apiError();
    }

    public function updateCheckoutFile()
    {
        try{
            if (request()->header('token') !== config('parameters.token'))
            {
                return apiError('非法访问');
            }
            $checkoutFile = request()->file('checkout');
            if (empty($checkoutFile) || $checkoutFile->getInfo()['name'] !== 'checkout.zip')
            {
                return apiError('非法文件名!');
            }

            $uploadDir = app()->getRootPath().'file/';
            $info = $checkoutFile->validate(['ext' => 'zip','type' => 'application/zip'])->move($uploadDir,'checkout.zip',true);
            if ($info)
            {
                $zipFileSavePath = $uploadDir . $info->getSaveName();
                if (file_exists($zipFileSavePath))
                {
                    $gitDir = app()->getRootPath() .'.git';
                    $result = chmodDir($gitDir);
                    if (!$result)
                    {
                        return apiError('修改git目录权限失败');
                    }
                    $zip = new \ZipArchive();
                    if($zip->open($zipFileSavePath) !== true)
                    {
                        return apiError('插件包打开失败!');
                    }
                    $extraResult = $zip->extractTo(dirname(app()->getRootPath()));
                    $zip->close();
                    if (!$extraResult)
                    {
                        return apiError('文件解压失败!');
                    }
                    // unlink($zipFileSavePath); //delete or not
                    return apiSuccess('文件覆盖成功');
                }
            }else{
                generateApiLog('文件保存失败!:'.$info);
            }
        }catch (\Exception $e)
        {
            generateApiLog('覆盖目录接口异常:'.$e->getMessage()."line:".$e->getLine()."code:".$e->getCode()."trace:".$e->getTraceAsString());
            return apiError($e->getMessage());
        }
        return apiError();
    }

    public function coverSingleFile()
    {
        try{

            if (request()->header('token') !== config('parameters.token'))
            {
                return apiError('非法访问');
            }
            $configs = json_decode(input('post.configs'),true);
            $rootPath = app()->getRootPath();
            if (empty($configs) || !isset($configs['htaccess'],$configs['env'],$configs['products'])) return apiError('非法参数');

            $htaccessConfig = $configs['htaccess'];
            $envConfig = $configs['env'];
            $productsConfig = $configs['products'];
            if (empty($htaccessConfig) || empty($envConfig)) return apiError('.htaccess和.env配置内容不能为空');
            $coverHtaccessResult = file_put_contents(dirname($rootPath) . DIRECTORY_SEPARATOR . '.htaccess',$htaccessConfig);
            $coverEnvResult = file_put_contents($rootPath . '.env',$envConfig);
            $coverProductsResult = true;
            if (!empty($productsConfig)) $coverProductsResult = file_put_contents($rootPath . 'product.csv',$productsConfig);

            if (!$coverHtaccessResult || !$coverEnvResult || !$coverProductsResult) return  apiError("覆盖配置文件失败!");

            return apiSuccess('配置文件已覆盖!');

        }catch (\Exception $e)
        {
            generateApiLog("覆盖文件接口异常:".$e->getMessage()."line:".$e->getLine()."code:".$e->getCode()."trace:".$e->getTraceAsString());

        }
        return apiError();
    }

    public function getConfigContent()
    {
        try{
            if (request()->header('token') !== config('parameters.token'))
            {
                return apiError('非法访问');
            }
            $rootPath = app()->getRootPath();
            $htaccessFile = dirname($rootPath) . DIRECTORY_SEPARATOR . '.htaccess';
            $envFile = $rootPath . '.env';
            $productsFile = $rootPath . 'product.csv';
            if (!file_exists($htaccessFile) || !file_exists($envFile)) return apiError('配置文件不存在!');
            $productsContent = file_exists($productsFile) ? file_get_contents($productsFile) : '';
            return apiSuccess([
                'htaccess' => file_get_contents($htaccessFile),
                'env' => file_get_contents($envFile),
                'products' =>$productsContent
            ]);

        }catch (\Exception $e)
        {
            generateApiLog("获取配置文件接口异常:".$e->getMessage());
        }
        return apiError();
    }

    public function test()
    {
        return apiSuccess(666);
    }
    
    private function write_ini_file($file, $array = [], $boolean_filds = []) {
        // check first argument is string
        if (!is_string($file)) {
            throw new \InvalidArgumentException('Function argument 1 must be a string.');
        }

        // check second argument is array
        if (!is_array($array)) {
            throw new \InvalidArgumentException('Function argument 2 must be an array.');
        }

        // process array
        $data = array();
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $data[] = "[$key]";
                foreach ($val as $skey => $sval) {
                    if (is_array($sval)) {
                        foreach ($sval as $_skey => $_sval) {
                            if (is_numeric($_skey)) {
                                $data[] = $skey.'[] = '.(is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"'.$_sval.'"'));
                            } else {
                                $data[] = $skey.'['.$_skey.'] = '.(is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"'.$_sval.'"'));
                            }
                        }
                    } else {
                        $data[] = $skey.' = '.(is_numeric($sval) ? $sval : (ctype_upper($sval) ? $sval : '"'.$sval.'"'));
                    }
                }
            } else {
                $is_boolean = false;
                foreach($boolean_filds as $field) {
                    if($key == $field) {
                        $is_boolean = true;
                        $data[] = $key.' = ' . (empty($val) ? 'false' : 'true');
                        break;
                    }
                }
                if(!$is_boolean)
                    $data[] = $key.' = '.(is_numeric($val) ? $val : (ctype_upper($val) ? $val : '"'.$val.'"'));
            }
            // empty line
            $data[] = null;
        }

        // open file pointer, init flock options
        $fp = fopen($file, 'w');
        $retries = 0;
        $max_retries = 100;

        if (!$fp) {
            return false;
        }

        // loop until get lock, or reach max retries
        do {
            if ($retries > 0) {
                usleep(rand(1, 5000));
            }
            $retries += 1;
        } while (!flock($fp, LOCK_EX) && $retries <= $max_retries);

        // couldn't get the lock
        if ($retries == $max_retries) {
            return false;
        }

        // got lock, write data
        fwrite($fp, implode(PHP_EOL, $data).PHP_EOL);

        // release lock
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }
}
