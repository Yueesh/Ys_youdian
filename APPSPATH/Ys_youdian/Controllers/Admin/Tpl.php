<?php namespace Phpcmf\Controllers\Admin;

/**
 * 转友点cms数据库
 * 开发：悦笙科技 http://www.yueesh.com
 */
class Tpl extends \Phpcmf\App
{
    private $old_path = APPSPATH . 'Ys_youdian/Cache/old/';
    private $new_path = APPSPATH . 'Ys_youdian/Cache/new/';
    private $dirs = [];
    public function __construct( ...$params )
    {
        parent::__construct( ...$params );

    }

    /**
     * 列出文件
     */
    private function listFilesAndFolders($dir) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {

                if (is_dir($dir . $file)) {
                     $this->listFilesAndFolders($dir . $file . '/');
                } else {
                    $dir2 = str_replace( $this->old_path, '', $dir );
                    $ext = str_replace( '.', '', trim( strtolower( strrchr( $file, '.' ) ), '.' ) );
                    if( $ext == 'html' ){
                        $this->dirs[] =  $dir2 . $file;
                        // $list .=  $dir2 . $file . PHP_EOL;
                    }else{ //删除非html文件
                        @unlink( $dir . $file );
                    }
                }
            }
        }
        return $list;
    }

    public function index()
    {
        \Phpcmf\Service::L('cache')->del_file('ys_ydtpl');
        if ( is_dir( $this->old_path ) ) {
            $this->listFilesAndFolders($this->old_path);
            \Phpcmf\Service::L('cache')->set_file('ys_ydtpl', $this->dirs);

        } else {
            $rt_mikdir = @mkdir( $this->old_path, 0755, true );
            if ( ! $rt_mikdir ) {
                $this->_html_msg( 0, '错误：文件夹 ' . $this->old_path . ' 不存在，且无法创建！' );
            }
        }

        XR_V()->assign( [
            'form' => dr_form_hidden(),
            'menu' => XR_M( 'auth' )->_admin_menu( [
                'YouDiancms 数据迁入' => ['ys_youdian/home/index', 'fa fa-database'],
                'YouDiancms 模板转换' => ['ys_youdian/tpl/index', 'fa fa-database'],
            ]
            ),
            'list' => implode(PHP_EOL, $this->dirs)
        ] );

        XR_V()->display( 'tpl.html' );
    }
    /**
     * 删除文件及子文件夹
     * @param string $dir
     */
    private function delete_files($dir) {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir($dir.$file)) ? $this->delete_files($dir.$file.'/') : unlink($dir.$file);
        }
        return rmdir($dir);
    }
    public function tpl_convert()
    {
        $page = (int) XR_L( 'input' )->get( 'page' );
        $is_function = [
            'ys_tpl' => function_exists('ys_tpl'),
            'url_rp' => function_exists('url_rp'),
            'my_const' => function_exists('my_const'),
        ];
        $dirs = \Phpcmf\Service::L('cache')->get_file('ys_ydtpl');
        $total = dr_count( $dirs );
        $to_url = dr_url(APP_DIR.'/tpl/tpl_convert');
        if(! $page){
            if ( is_dir( $this->new_path ) ) {// 清空 cache/new文件夹
                $this->delete_files( $this->new_path );
            } else {
                $rt = @mkdir( $this->new_path, 0755, true );
                if ( ! $rt ) {
                    $this->_admin_msg( 0, '错误：文件夹 ' . $this->new_path . ' 不可写2！' );
                }
            }
            $this->_admin_msg( 1, dr_lang( '正在转换模板文件【%s】...', "$page/$total" ) , $to_url . '&page=1', 0 );
        }
        // 更新完成
        if ( $page > $total ) {
            $this->_admin_msg( 1, '模板文件转换成功', dr_url( APP_DIR . '/tpl/index' ) );
        }

        $dir = $dirs[$page-1];

        if ( $dir != '.' && $dir != '..' ) {
            $file     = file_get_contents( $this->old_path . $dir );
            if( $file ){

                $result = $this->curlPost( ['is_function' => $is_function, 'html' => $file] );
                if( ! $result['code'] ){
                    $this->_admin_msg( 0, $result['msg'].'--'.$dir );
                }

                $w_dir = dirname($this->new_path . $dir);
                if ( ! is_dir( $w_dir ) ){
                    $rt = @mkdir( $w_dir, 0755, true );
                    if ( ! $rt ) {
                        $this->_admin_msg( 0, '错误：文件夹 ' . $this->new_path . ' 不可写4！'.$dir );
                    }
                }
                $rt = file_put_contents( $this->new_path . $dir, $result['data'] );
                if ( ! $rt ) {
                    $this->_admin_msg( 0, '错误：文件夹 ' . $this->new_path . ' 不可写3！'.$dir );
                }
            }
        }
        $this->_admin_msg( 1, dr_lang( '正在转换模板文件【%s】...', "$page/$total" ) , $to_url . '&page='.($page+1), 0 );

    }





    public function test()
    {
        $file     = file_get_contents( $this->old_path . 'index.html' );

        $is_function = [
            'ys_tpl' => function_exists('ys_tpl'),
            'url_rp' => function_exists('url_rp'),
            'my_const' => function_exists('my_const'),
        ];
        $rt = $this->curlPost( ['is_function' => $is_function, 'html' => $file] );
        print_r($rt);
    }


    protected function curlPost(  $data )
    {
        $url = 'http://convert.yueesh.cn/index.php?s=ys_convert&c=home&m=convert';
        $data = http_build_query( $data );
        $ch   = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 300 ); // 设置超时时间（秒）

        // curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        $response      = curl_exec( $ch );
        $response_data = json_decode( $response, true );
        if ( curl_errno( $ch ) ) {
            $result = 'Error: ' . curl_error( $ch );
        } else {
            $result = $response_data;
        }
        curl_close( $ch );
        return $result;
    }
}
