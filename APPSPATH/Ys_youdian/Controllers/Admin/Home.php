<?php namespace Phpcmf\Controllers\Admin;

/**
 * 转友点cms数据库
 * 开发：悦笙科技 http://www.yueesh.com
 * ？？？？   “”
 */
class Home extends \Phpcmf\App
{
    private $is_down;
    private $old_domain;
    private $custom   = [];
    private $mods     = [];
    private $validate = [];
    public function __construct( ...$params )
    {
        parent::__construct( ...$params );
        if ( is_file( WRITEPATH . 'config/yueesh_ydtocy.php' ) ) {
            $this->custom = require WRITEPATH . 'config/yueesh_ydtocy.php';
        }
        if ( $this->custom ) {
            $this->is_down    = $this->custom['is_down'];
            $this->old_domain = $this->custom['old_domain'];
        }
        $this->custom['time'] = $this->custom['time'] ? $this->custom['time'] : 10;
        $this->validate       = [
            'xss'       => '1',
            'required'  => '0',
            'pattern'   => '',
            'errortips' => '',
            'check'     => '',
            'filter'    => '',
            'formattr'  => '',
            'tips'      => ''
        ];
        XR_V()->assign( [
            'form' => dr_form_hidden(),
            'menu' => XR_M( 'auth' )->_admin_menu( [
                'YouDiancms 数据迁入' => [APP_DIR . '/' . XR_L( 'Router' )->class . '/index', 'fa fa-database'],
                'YouDiancms 模板转换' => ['ys_youdian/tpl/index', 'fa fa-database']
            ]
            )
        ] );
    }

    public function index()
    {
        if ( IS_POST ) {
            $post = XR_L( 'input' )->post( 'data' );
            if (  ! $post['host'] ) {
                $this->_json( 0, '数据库信息必须填写' );
            }
            $old_domain = rtrim( trim( $post['old_domain'], '/' ) );
            $custom     = [
                'DSN'        => '',
                'hostname'   => $post['host'],
                'username'   => $post['user'],
                'password'   => $post['pass'],
                'database'   => $post['name'],
                'DBDriver'   => 'MySQLi',
                'DBPrefix'   => $post['prefix'],
                'pConnect'   => false,
                'DBDebug'    => false,
                'cacheOn'    => false,
                'cacheDir'   => '',
                'charset'    => 'utf8',
                'DBCollat'   => 'utf8_general_ci',
                'swapPre'    => '',
                'encrypt'    => false,
                'compress'   => false,
                'strictOn'   => false,
                'failover'   => [],
                'port'       => $post['prot'],
                'is_down'    => $post['is_down'] == 'on' ? 1 : 0,
                'old_domain' => $old_domain,
                'time'       => intval( $post['time'] )
            ];

            $db = \Config\Database::connect( $custom );
            if ( $db->simpleQuery( 'SELECT * FROM `' . $post['prefix'] . 'channel`' ) ) {
                $custom['DBDebug'] = true;
                file_put_contents( WRITEPATH . 'config/yueesh_ydtocy.php', '<?php return ' . var_export( $custom, true ) . ';' );
                $this->_json( 1, '数据库识别成功', [
                    'url' => dr_url( APP_DIR . '/home/add' )
                ] );
            } else {
                $this->_json( 0, '数据库识别失败,检查表前缀是否正确' );
            }
        }

        XR_V()->assign( [
            'data' => $this->custom
        ] );

        XR_V()->display( 'home.html' );
    }

    public function clear_failimgs()
    {
        if ( IS_POST ) {
            XR_L( 'cache' )->del_file( 'ys_youdian_failimgs' );
            $this->_json( 1, '清除成功', [
                'url' => dr_url( APP_DIR . '/home/add' )
            ] );
        }
    }

    public function add()
    {
        $table = [
            ['name' => '中文栏目表'],
            ['name' => '英文栏目表']
        ];

        foreach ( $table as $i => $t ) {
            $table[$i]['total'] = $this->_db()->table( 'channel' )
                ->where( 'languageid=' . ( $i + 1 ) )
                ->where( 'channelid != 1' )
                ->where( 'channelid != 2' )
                ->where( 'channelid != 6' )
                ->where( 'channelid != 7' )
                ->where( 'channelid != 11' )
                ->where( 'channelid != 10' )
                ->where( 'IsEnable', 1 )
                ->countAllResults();
        }

        $module = [
            ['name' => '中文信息表'],
            ['name' => '英文信息表']
        ];
        foreach ( $module as $i => $t ) {
            $module[$i]['total'] = $this->_db()->table( 'info' )->where( 'languageid', ( $i + 1 ) )->countAllResults();
        }
        $ys_youdian_failimgs = XR_L( 'cache' )->get_file( 'ys_youdian_failimgs' );
        XR_V()->assign( [
            'table'      => $table,
            'module'     => $module,
            'old_domain' => $this->custom['old_domain'],
            'failimgs'   => $ys_youdian_failimgs,
            'time'       => $this->custom['time'],
            'webset'     => [
                ['name' => '中文配置表'],
                ['name' => '英文配置表']
            ]
        ] );

        XR_V()->display( 'add.html' );
    }

    /**
     * 栏目及信息
     */
    public function edit()
    {
        set_time_limit( 0 );
        $lang = (int) XR_L( 'input' )->get( 'lang' );
        $st   = (int) XR_L( 'input' )->get( 'st' );
        $page = (int) XR_L( 'input' )->get( 'page' );

        /**去除单页、链接、反馈模型 */
        $row = $this->_db()->table( 'channel' )
            ->where( 'languageid', $lang )
            ->where( 'ChannelModelID > 29' )
            ->where( 'ChannelModelID != 32 and ChannelModelID != 33 and ChannelModelID != 37' )
            ->where( 'IsEnable', 1 )
            ->select( 'ChannelModelID' )->orderBy( 'ChannelModelID ASC' )->get()->getResultArray();

        $row = array_column( $row, null, 'ChannelModelID' ); //以ID为索引
        $row = array_values( $row ); //去除关联索引

        if (  ! dr_count( $row ) ) {
            $this->_admin_msg( 0, dr_lang( '无可用模型' ) );
        }

        foreach ( $row as $key => $v ) {
            if ( $v['ChannelModelID'] == 30 ) {
                $this->mods[] = [
                    'nid'            => 'news',
                    'typename'       => '文章',
                    'ChannelModelID' => 30
                ];
            } elseif ( $v['ChannelModelID'] == 36 ) {
                $this->mods[] = [
                    'nid'            => 'product',
                    'typename'       => '产品',
                    'ChannelModelID' => 36
                ];
            } elseif ( $v['ChannelModelID'] == 31 ) {
                $this->mods[] = [
                    'nid'            => 'picture',
                    'typename'       => '图片',
                    'ChannelModelID' => 31
                ];
            } elseif ( $v['ChannelModelID'] == 34 ) {
                $this->mods[] = [
                    'nid'            => 'video',
                    'typename'       => '视频',
                    'ChannelModelID' => 34
                ];
            } elseif ( $v['ChannelModelID'] == 35 ) {
                $this->mods[] = [
                    'nid'            => 'down',
                    'typename'       => '下载',
                    'ChannelModelID' => 35
                ];
            } else {

                $_mod = $this->_db()->table( 'channel_model' )->where( 'ChannelModelID', $v['ChannelModelID'] )->select( 'ChannelModelName' )->get()->getRowArray(); //模型列表
                /**模型名称转成拼音，并删除除了字母的其它字符 */
                $_name = str_replace( '模型', '', $_mod['ChannelModelName'] );
                $_nid  = XR_L( 'pinyin' )->result( $_name );
                $_nid  = preg_replace( '/[\W|\d]/', '', $_nid ); //

                $this->mods[] = [
                    'nid'            => $_nid,
                    'typename'       => $_name,
                    'ChannelModelID' => $v['ChannelModelID']
                ];
            }
        }

        if ( $st == 0 ) {
            // 栏目库

            $to_url = dr_url( APP_DIR . '/home/edit', ['st' => 0, 'page' => 1, 'lang' => $lang] );
            $cpage  = intval( XR_L( 'input' )->get( 'cpage' ) );

            $channeldb = $this->_db()->table( 'channel' )
                ->where( 'languageid', $lang )
                ->where( 'channelid != 1' )
                ->where( 'channelid != 2' )
                ->where( 'channelid != 6' )
                ->where( 'channelid != 7' )
                ->where( 'channelid != 11' )
                ->where( 'channelid != 10' )
                ->where( 'IsEnable', 1 );
            if (  ! $cpage ) {
                // 计算数量
                $total = $channeldb->countAllResults();
                if (  ! $total ) {
                    $this->_admin_msg( 0, dr_lang( '无可用内容' ) );
                }
                $this->_admin_msg( 1, dr_lang( '正在转入栏目数据...' ), $to_url . '&total=' . $total . '&cpage=' . ( $cpage + 1 ) );
            }

            $psize = $this->is_down ? 1 : 20; //不下载远程文件每次20，下载远程文件每次1条
            $total = (int) XR_L( 'input' )->get( 'total' );
            $tpage = ceil( $total / $psize ); // 总页数

            // 更新完成
            if ( $cpage > $tpage ) {
                XR_M( 'cache' )->sync_cache( '' ); // 自动更新缓存
                $this->_admin_msg( 1, '导入成功', dr_url( APP_DIR . '/home/add' ) );
            }

            $list   = $channeldb->limit( $psize, $psize * ( $cpage - 1 ) )->get()->getResultArray();
            $ntable = XR_M()->dbprefix( SITE_ID . '_share_category' );

            $fields = $this->_fields( 1, $lang );

            if ( $cpage == 1 ) {
                //第一次，清空栏目，创建栏目字段

                $table = XR_M()->prefix . SITE_ID . '_share_category';
                $this->_set_fields( $fields, $table, 0, 'category-share' ); //创建栏目字段

                // 创建简短内容字段
                if (  ! \Phpcmf\Service::M()->db->fieldExists( "scontent", $table ) ) {
                    \Phpcmf\Service::M()->query( 'ALTER TABLE `' . $table . '` ADD `scontent` TEXT DEFAULT NULL COMMENT "简短内容"' );
                }
                $this->_add_field( '简短内容', 'scontent', 0, 'Textarea', '', 'category-share' );

                /**清空栏目数据表 */
                $sql = 'TRUNCATE `' . $ntable . '`';
                $r1  = XR_M()->query( $sql );

            }

            if ( $list ) {
                foreach ( $list as $t ) {
                    $dir           = trim( $t['Html'] );
                    $tid           = 1;
                    $url           = '';
                    $IndexTemplate = $t['IndexTemplate']; //列表模板
                    $ReadTemplate  = $t['ReadTemplate']; //阅读模板
                    $PageSize      = $t['PageSize']; //分页数

                    $c   = array_search( $t['ChannelModelID'], array_column( $this->mods, 'ChannelModelID' ) );
                    $mid = $c !== false ? $this->mods[$c]['nid'] : ''; //模型英文名称

                    if ( $t['ChannelModelID'] == 32 ) {
                        //单页
                        $tid = 0;
                    } elseif ( $t['ChannelModelID'] == 33 ) {
                        //链接
                        $tid = 2;
                        $url = $t['LinkUrl'];
                    }
                    // _saveimg( $id, $url, $table, $date = '' )

                    $thumb = (String) $t['ChannelPicture'];
                    if ( $thumb && $this->is_down ) {
                        //下载远程缩略图
                        $thumb = $this->_saveimg( $t['ChannelID'], $t['ChannelPicture'], 'share_category' );
                    }
                    $content = $t['ChannelContent'];
                    $content = $this->special_html( $content ); //字符转换
                    if ( $this->is_down ) {
                        //
                        // $content = str_replace( "&amp;", '&', $content );
                        // $imgs = dr_get_content_img( $content );
                        // 系统取图片函数改成用正则获取
                        preg_match_all( '/<img[^>]* src="([^"]*)"[^>]*>/i', $content, $matchs );
                        $imgs = $matchs[1];

                        foreach ( $imgs as $img ) {
                            if ( $img ) {
                                $img     = explode( '?', $img )[0];
                                $img     = str_replace( SITE_URL, '/', $img );
                                $_img    = $this->_saveimg( $t['ChannelID'], $img, 'share_category' );
                                $content = str_replace( $img, dr_get_file( $_img ), $content );
                            }
                        }

                        // 编辑器中的图片转存本地
                        // $content = $this->_content( $content, 'share_category', $t['ChannelID'], '' );


                    }
                    // XR_L('cache')->set_file('m2', [$t['ChannelID'],$thumb]);
                    $save    = [];
                    $save[1] = [
                        'id'           => $t['ChannelID'],
                        'pid'          => $t['Parent'],
                        'name'         => $t['ChannelName'],
                        'dirname'      => $dir,
                        'mid'          => $mid,
                        'displayorder' => intval( $t['ChannelOrder'] ),
                        'tid'          => $tid,
                        'pids'         => '',
                        'pdirname'     => '',
                        'childids'     => '',
                        'thumb'        => $thumb,
                        'show'         => $t['IsShow'],
                        'scontent'     => $t['ChannelSContent'],
                        'content'      => $content,
                        'setting'      => dr_array2string(
                            array(
                                'disabled'     => '0',
                                'linkurl'      => $url,
                                'urlrule'      => '0',
                                'seo'          => array(
                                    'list_title'       => $t['seotitle'] ? $t['seotitle'] : '[第{page}页{join}]{catpname}{join}{SITE_NAME}',
                                    'list_keywords'    => $t['keywords'] ? $t['keywords'] : '',
                                    'list_description' => $t['description'] ? $t['description'] : ''
                                ),
                                'template'     => array(
                                    'pagesize'  => 12,
                                    'mpagesize' => 12,
                                    'page'      => $IndexTemplate,
                                    'list'      => 'list.html',
                                    'category'  => 'category.html',
                                    'search'    => 'search.html',
                                    'show'      => 'show.html'
                                ),
                                'cat_field'    => '',
                                'module_field' => ''
                            ) )
                    ];
                    if ( $fields ) {
                        //其它字段入库
                        $save = $this->_in_fields( $save, $fields, $t, $t['ChannelID'], 'share_category', dr_date( SYS_TIME, 'Ym' ) );
                    }

                    // print_r([$save, $fields, $t]);exit;

                    XR_M()->db->table( $ntable )->replace( $save[1] );
                }

            }
            $this->_admin_msg( 1, dr_lang( '正在转入栏目数据【%s】...', "$tpage/$cpage" ) . $t['ChannelID'], $to_url . '&total=' . $total . '&cpage=' . ( $cpage + 1 ), 0 );

        } else {
            // 内容库
            if (  ! $page ) {
                /**清空共享索引表 */
                $itable = XR_M()->dbprefix( SITE_ID . '_share_index' );
                $sql    = 'TRUNCATE `' . $itable . '`';
                $r2     = XR_M()->query( $sql );
                // 创建模块
                foreach ( $this->mods as $mod ) {
                    if (  ! dr_is_module( $mod['nid'] ) ) {
                        // 创建模块
                        if ( in_array( $mod['nid'], ['case', 'class', 'extends', 'site',
                            'new', 'var', 'member', 'category', 'linkage', 'api',
                            'module', 'form', 'admin', 'weixin'] ) ) {
                            $this->_admin_msg( 0, $mod['nid'] . '是系统保留的关键字，建议手动修改表数据和表名称' );
                        }
                        if (  ! preg_match( '/^[a-z]+$/i', $mod['nid'] ) ) {
                            $this->_admin_msg( 0, dr_lang( $mod['nid'] . '只能是英文字母，不能带数字或其他符号，建议手动修改表数据和表名称' ) );
                        }
                        // 开始复制到指定目录
                        $path  = APPSPATH . ucfirst( $mod['nid'] ) . '/';
                        $xpath = dr_get_app_dir( 'module' ) . 'Temps/Module/';
                        if (  ! is_file( $xpath . 'Config/App.php' ) ) {
                            $this->_admin_msg( 0, dr_lang( '文件（' . $xpath . '）不存在，检查CMS版本是否最新版' ), ['field' => 'dirname'] );
                        }
                        XR_L( 'File' )->copy_file( $xpath, $path );
                        if (  ! is_file( $path . 'Config/App.php' ) ) {
                            $this->_admin_msg( 0, dr_lang( '目录（' . $path . '）创建失败，请检查文件权限' ), ['field' => 'dirname'] );
                        }

                        // 替换模块配置文件
                        $app = file_get_contents( $path . 'Config/App.php' );
                        $app = str_replace( ['{name}', '{icon}'], [dr_safe_filename( $mod['typename'] ), 'fa fa-code'], $app );
                        file_put_contents( $path . 'Config/App.php', $app );

                        // 安装模块
                        $cfg = require $path . 'Config/App.php';
                        if (  ! $cfg ) {
                            $this->_admin_msg( 0, dr_lang( '文件[%s]不存在', 'App/' . ucfirst( $mod['nid'] ) . '/Config/App.php' ) );
                        }
                        $cfg['share'] = 1;
                        $rt           = XR_M( 'module' )->install( $mod['nid'], $cfg );
                         ! $rt['code'] && $this->_admin_msg( $rt['code'], $rt['msg'] );

                        //配置模块
                        $module  = XR_M()->db->table( 'module' )->where( 'dirname', $mod['nid'] )->get()->getRowArray();
                        $setting = json_decode( $module['setting'], true );
                        // print_r( $setting );exit;
                        $func                   = function_exists( 'my_title' ) ? 'my_title' : 'title';
                        $setting['desc_auto']   = '1';
                        $setting['order']       = 'displayorder DESC,inputtime DESC';
                        $setting['search_time'] = 'inputtime';
                        // $setting['updatetime_select'] = '1';
                        $setting['list_field'] = [
                            'id'           => [
                                'use'   => '1',
                                'name'  => 'Id',
                                'width' => '60',
                                'func'  => ''
                            ],
                            'displayorder' => [
                                'use'   => '1',
                                'name'  => '排列值',
                                'width' => '80',
                                'func'  => 'save_text_value'
                            ],
                            'title'        => [
                                'use'   => '1',
                                'name'  => '主题',
                                'width' => '',
                                'func'  => $func
                            ],
                            'catid'        => [
                                'use'   => '1',
                                'name'  => '栏目',
                                'width' => '130',
                                'func'  => 'catid'
                            ],
                            'author'       => [
                                'use'   => '1',
                                'name'  => '笔名',
                                'width' => '120',
                                'func'  => 'author'
                            ],
                            'updatetime'   => [
                                'use'   => '1',
                                'name'  => '更新时间',
                                'width' => '160',
                                'func'  => 'datetime'
                            ]
                        ];

                        XR_M()->db->table( 'module' )->where( 'dirname', $mod['nid'] )->update( ['setting' => dr_array2string( $setting )] );
                    }
                }

                XR_M( 'cache' )->sync_cache( '' ); // 自动更新缓存
                $this->_admin_msg( 1, '模块创建成功', dr_url( APP_DIR . '/home/edit', ['st' => $st, 'page' => 1, 'lang' => $lang] ), 1 );
            } elseif ( $page == 1 ) {
                // 创建信息字段
                foreach ( $this->mods as $mod ) {
                    $module = XR_M()->db->table( 'module' )->where( 'dirname', $mod['nid'] )->get()->getRowArray();
                    $fields = $this->_fields( $mod['ChannelModelID'], $lang );
                    $table  = XR_M()->prefix . SITE_ID . '_' . $mod['nid'];
                    $this->_set_fields( $fields, $table, $module['id'], 'module' ); //创建字段
                }
                $this->_admin_msg( 1, '字段创建成功', dr_url( APP_DIR . '/home/edit', ['st' => $st, 'page' => 2, 'lang' => $lang] ), 1 );
            } elseif ( $page == 2 ) {
                // 入库数据
                $to_url = dr_url( APP_DIR . '/home/edit', ['st' => $st, 'page' => 2, 'lang' => $lang] );
                $cpage  = intval( XR_L( 'input' )->get( 'cpage' ) );
                $table  = $this->_prefix( '' ) . 'info';
                if (  ! $cpage ) {
                    // 计算数量
                    $total = $this->_db()->table( $table )->where( 'LanguageID', $lang )->where( 'IsEnable', 1 )->where( 'IsCheck', 1 )->countAllResults();
                    if (  ! $total ) {
                        $this->_admin_msg( 0, dr_lang( '无可用内容' ) );
                    }
                    $this->_admin_msg( 1, dr_lang( '正在执行中...' ), $to_url . '&total=' . $total . '&cpage=' . ( $cpage + 1 ) );
                }

                $psize = $this->is_down ? 1 : 20; //不下载远程文件每次20，下载远程文件每次1条
                $total = (int) XR_L( 'input' )->get( 'total' );
                $tpage = ceil( $total / $psize ); // 总页数

                // 更新完成
                if ( $cpage > $tpage ) {
                    XR_M( 'cache' )->sync_cache( '' ); // 自动更新缓存
                    $this->_admin_msg( 1, dr_lang( '入库完成' ), dr_url( APP_DIR . '/home/add' ) );
                }

                $list = $this->_db()->table( $table )
                    ->where( 'LanguageID', $lang )
                    ->where( 'IsEnable', 1 )
                    ->where( 'IsCheck', 1 )
                    ->limit( $psize, $psize * ( $cpage - 1 ) )
                    ->orderBy( 'InfoID DESC' )
                    ->get()
                    ->getResultArray();

                // $catlist = XR_M()->db->table( SITE_ID .'_share_category')->where('mid != ""')->select('id, mid')->get()->getResultArray();

                foreach ( $list as $row ) {
                    $channel          = $this->_db()->table( 'channel' )->where( 'ChannelID', $row['ChannelID'] )->select( 'ChannelModelID' )->get()->getRowArray(); //栏目列表
                    $channel_model_id = $channel['ChannelModelID']; //模型ID号
                    $c                = array_search( $channel_model_id, array_column( $this->mods, 'ChannelModelID' ) );
                    $mid              = $this->mods[$c]['nid']; //模型英文名
                    $fields           = $this->_fields( $channel_model_id, $lang );

                    // print_r($fields);exit;

                    $thumb = (String) $row['InfoPicture'];
                    if ( $thumb && $this->is_down ) {
                        //下载远程缩略图
                        $thumb = $this->_saveimg( $row['InfoID'], $row['InfoPicture'], $mid, dr_date( strtotime( $row['InfoTime'] ), 'Ym' ) );
                    }

                    $content = $row['InfoContent'];
                    $content = $this->special_html( $content );
                    if ( $this->is_down ) {
                        // 编辑器中的图片转存本地
                        $content = $this->_content( $content, $mid, $row['InfoID'], dr_date( strtotime( $row['InfoTime'] ), 'Ym' ) );
                    }

                    $save = [
                        1 => [
                            'id'           => $row['InfoID'],
                            'catid'        => $row['ChannelID'],
                            'hits'         => $row['InfoHit'],
                            'title'        => $row['InfoTitle'],
                            'thumb'        => $thumb,
                            'uid'          => $this->uid,
                            'url'          => '/index.php?c=show&id=' . $row['InfoID'],
                            'author'       => $row['InfoAuthor'] ? $row['InfoAuthor'] : $this->member['username'],
                            'keywords'     => $row['Keywords'],
                            'description'  => $row['InfoSContent'],
                            'inputtime'    => strtotime( $row['InfoTime'] ),
                            'updatetime'   => strtotime( $row['InfoTime'] ),
                            'status'       => 9,
                            'tableid'      => 0,
                            'displayorder' => intval( $row['InfoOrder'] )
                        ],
                        0 => [
                            'id'      => $row['InfoID'],
                            'catid'   => $row['ChannelID'],
                            'uid'     => $this->uid,
                            'content' => $content
                            // 'content' => htmlspecialchars( $content ),
                        ]
                    ];

                    if ( $fields ) {
                        //其它字段入库
                        $save = $this->_in_fields( $save, $fields, $row, $row['InfoID'], $mid, dr_date( strtotime( $row['InfoTime'] ), 'Ym' ) );
                    }

                    $this->_save_content( $mid, $save );

                }

                $this->_admin_msg( 1, dr_lang( '正在执行中【%s】...', "$tpage/$cpage" ), $to_url . '&total=' . $total . '&cpage=' . ( $cpage + 1 ), 0 );
            }
        }

    }

    /**
     * 网站配置及变量
     */
    public function webset()
    {
        $lang = (int) XR_L( 'input' )->get( 'lang' );
        $st   = (int) XR_L( 'input' )->get( 'st' );
        $page = (int) XR_L( 'input' )->get( 'page' );

        if (  ! $page ) {
            $this->_admin_msg( 1, '网站配置导入中...', dr_url( APP_DIR . '/home/webset', ['page' => 1, 'st' => 1, 'lang' => $lang] ) );
        }
        $setting = XR_M()->db->table( 'site' )->where( 'id', SITE_ID )->get()->getRowArray();
        $setting = json_decode( $setting['setting'], true );

        $bans  = $this->_db()->table( 'banner' )->where( 'languageid', $lang )->where( 'IsEnable', 1 )->get()->getResultArray();
        $links = $this->_db()->table( 'link' )->where( 'languageid', $lang )->where( 'IsEnable', 1 )->get()->getResultArray();

        $fields = [
            [
                'cname' => 'Banner',
                'name'  => 'banner',
                'field' => [
                    ['type' => '3', 'name' => 'PC图片'],
                    ['type' => '3', 'name' => '手机图'],
                    ['type' => '1', 'name' => '名称'],
                    ['type' => '1', 'name' => '链接'],
                    ['type' => '7', 'name' => '描述']
                ]
            ],
            [
                'cname' => '友情链接',
                'name'  => 'link',
                'field' => [
                    ['type' => '1', 'name' => '名称'],
                    ['type' => '1', 'name' => '链接'],
                    ['type' => '3', 'name' => '图片']
                ]
            ]
        ];

        foreach ( $fields as $n ) {
            $option = [];
            foreach ( $n['field'] as $key => $v ) {
                $v['width']       = '';
                $v['option']      = '';
                $option[$key + 1] = $v;
            }
            $fields_setting = [
                'option'   => [
                    'is_add'        => '1',
                    'is_first_hang' => '0',
                    'count'         => '',
                    'first_cname'   => '',
                    'hang'          => '',
                    'field'         => $option
                ],
                'validate' => $this->validate
            ];

            $this->_add_field( $n['cname'], $n['name'], SITE_ID, 'Ftable', dr_array2string( $fields_setting ), 'site' );
        }
        /**Banner幻灯 */
        $banners = [];
        $pc      = 0;
        $m       = 0;
        foreach ( $bans as $v ) {
            if ( $v['BannerGroupID'] == 1 || $v['BannerGroupID'] == 2 ) {
                $pc++;
                $thumb_pc = $v['BannerImage'];
                if ( $thumb_pc && $this->is_down ) {
                    //下载远程缩略图
                    $thumb_pc = $this->_saveimg( 'banner-pc', $thumb_pc, 'site' );
                }
                $banners[$pc][1] = $thumb_pc;
                $banners[$pc][3] = $v['BannerName'];
                $banners[$pc][4] = $v['BannerUrl'];
                $banners[$pc][5] = $v['BannerDescription'];
                // [
                //     1 => $thumb_pc,
                //     // 2 => '',
                //     3 => $v['BannerName'],
                //     4 => $v['BannerUrl'],
                //     5 => $v['BannerDescription']
                // ];
            }
            if ( $v['BannerGroupID'] == 3 || $v['BannerGroupID'] == 4 ) {
                $m++;
                $thumb_m = $v['BannerImage'];
                if ( $thumb_m && $this->is_down ) {
                    //下载远程缩略图
                    $thumb_m = $this->_saveimg( 'banner-m', $thumb_m, 'site' );
                }
                $banners[$m][2] = $thumb_m;
                // if ( $banners[$m] && is_array( $banners[$m + 1] ) ) {
                // }
            }
        }
        // print_r($banners);exit;
        $setting['param']['banner']  = dr_array2string( $banners );
        $setting['config']['banner'] = $banners;

        /**友情链接 */
        $lis = [];
        foreach ( $links as $key => $l ) {
            $link_logo = $l['LinkLogo'];
            if ( $link_logo && $this->is_down ) {
                $link_logo = $this->_saveimg( 'link', $link_logo, 'site' );
            }
            $lis[$key + 1] = [
                1 => $l['LinkName'],
                2 => $l['LinkUrl'],
                3 => $link_logo
            ];

        }
        $setting['param']['link']  = dr_array2string( $lis );
        $setting['config']['link'] = $lis;

        $rows = $this->_db()->table( 'config' )->whereIn( 'languageid', [0, $lang] )->get()->getResultArray();
        $arr  = [];
        foreach ( $rows as $r ) {
            $arr[$r['ConfigName']] = $r['ConfigValue'];
        }

        $logo = (String) $arr['WEB_LOGO'];
        if ( $logo && $this->is_down ) {
            $logo = $this->_saveimg( 'logo', $logo, 'site' );
        }
        $erweima = $arr['WX_QRCODE'];
        if ( $erweima && $this->is_down ) {
            $erweima = $this->_saveimg( 'erweima', $erweima, 'site' );
        }

        $setting['config']['logo']        = $logo;
        $setting['config']['SITE_NAME']   = $arr['WEB_NAME'];
        $setting['config']['SITE_ICP']    = $arr['WEB_ICP'];
        $setting['config']['SITE_TONGJI'] = $arr['STAT_CODE'] . $arr['ASYNC_STAT_CODE'];

        $ditubiaozhu = ( $arr['Longitude'] && $arr['Latitude'] ) ? $arr['Longitude'] . ',' . $arr['Latitude'] : '';
        $field       = [
            'company'     => ['FieldName' => 'company', 'DisplayName' => '公司名称', 'value' => $arr['COMPANY'], 'DisplayType' => 'text'],
            'contact'     => ['FieldName' => 'contact', 'DisplayName' => '联系人', 'value' => $arr['CONTACT'], 'DisplayType' => 'text'],
            'mobile'      => ['FieldName' => 'mobile', 'DisplayName' => '手机', 'value' => $arr['MOBILE'], 'DisplayType' => 'text'],
            'telephone'   => ['FieldName' => 'telephone', 'DisplayName' => '电话', 'value' => $arr['TELEPHONE'], 'DisplayType' => 'text'],
            'fax'         => ['FieldName' => 'fax', 'DisplayName' => '传真', 'value' => $arr['FAX'], 'DisplayType' => 'text'],
            'email'       => ['FieldName' => 'email', 'DisplayName' => '邮箱', 'value' => $arr['EMAIL'], 'DisplayType' => 'text'],
            'postcode'    => ['FieldName' => 'postcode', 'DisplayName' => '邮编', 'value' => $arr['POSTCODE'], 'DisplayType' => 'text'],
            'address'     => ['FieldName' => 'address', 'DisplayName' => '地址', 'value' => $arr['ADDRESS'], 'DisplayType' => 'text'],
            'erweima'     => ['FieldName' => 'erweima', 'DisplayName' => '二维码', 'value' => $erweima, 'DisplayType' => 'image'],
            'ditubiaozhu' => ['FieldName' => 'ditubiaozhu', 'DisplayName' => '地图标注', 'value' => $ditubiaozhu, 'DisplayType' => 'text']

        ];

        /**有没有安装《模板设置》插件 */
        if ( function_exists( 'ys_tpl' ) ) {
            $ys_tpl_rows = XR_M()->db->table( SITE_ID . '_ys_tpl' )->get()->getResultArray();
            $ys_tpl_data = [];
            foreach ( $field as $fieldname => $v ) {
                if (  ! $v['value'] ) {
                    continue; //无值跳出
                }
                $c = array_search( $fieldname, array_column( $ys_tpl_rows, 'code' ) );
                if ( $c !== false ) {
                    //字段已存在
                    $ys_tpl_data[] = [
                        'id'      => $ys_tpl_rows[$c]['id'],
                        'content' => ( $v['DisplayType'] == 'image' ? '{i-3}:' : '{i-0}:' ) . $v['value']
                    ];
                } else {
                    XR_M()->db->table( SITE_ID . '_ys_tpl' )->insert( array(
                        'name'    => $v['DisplayName'],
                        'code'    => $fieldname,
                        'hide'    => 0,
                        'content' => ( $v['DisplayType'] == 'image' ? '{i-3}:' : '{i-0}:' ) . $v['value']
                    ) );
                }
            }
            $ys_tpl_data && XR_M()->db->table( SITE_ID . '_ys_tpl' )->updateBatch( $ys_tpl_data, 'id' );
        } else {
            $fields = [];
            foreach ( $field as $fieldname => $v ) {
                if (  ! $v['value'] ) {
                    continue; //无值跳出
                }
                $fields[]                      = $field[$fieldname];
                $setting['config'][$fieldname] = $v['value'];
                $setting['param'][$fieldname]  = $v['value'];
            }
            $this->_set_fields( $fields, '', SITE_ID, 'site', 0 ); // 创建字段 $setting
        }

        $data = [
            'name'    => $arr['WEB_NAME'],
            // 'domain' => str_ireplace('https://', '', str_ireplace('http://', '', $arr['WEB_URL'])), 域名不导入
            'setting' => dr_array2string( $setting )
        ];
        XR_M()->db->table( 'site' )->where( 'id', SITE_ID )->update( $data );
        XR_M( 'cache' )->sync_cache( '' ); // 自动更新缓存
        $this->_admin_msg( 1, '导入成功', dr_url( APP_DIR . '/home/add' ) );
    }

    /**
     * 创建字段
     */
    private function _set_fields( $fields, $table, $id, $relatedname, $is_tb = '1' )
    {

        $setting = [
            'radio'   => [ //单选 下拉
                'option'   => [
                    'options'     => '',
                    'is_field_ld' => '0',
                    'value'       => '',
                    'fieldtype'   => '',
                    'fieldlength' => '',
                    'show_type'   => '',
                    'css'         => ''
                ],
                'validate' => $this->validate
            ],
            'files'   => [ //多文件
                'option'   => [
                    'input'        => '1',
                    'name'         => '1',
                    'desc'         => '1',
                    'size'         => '10',
                    'count'        => '20',
                    'ext'          => 'jpg,gif,png,webp,jpeg,svg,mp4,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,7z,txt,exe',
                    'attachment'   => '0',
                    'image_reduce' => '',
                    'is_ext_tips'  => '0',
                    'css'          => ''
                ],
                'validate' => $this->validate
            ],

            'file'    => [ //单文件
                'option'   => array(
                    'input'        => '1',
                    'size'         => '10',
                    'count'        => '20',
                    'ext'          => 'jpg,gif,png,webp,jpeg,svg,mp4,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,7z,txt,exe',
                    'attachment'   => '0',
                    'image_reduce' => '',
                    'is_ext_tips'  => '0',
                    'css'          => ''
                ),
                'validate' => $this->validate
            ],
            'editor'  => [ //编辑器
                'option'   => [
                    'down_img'     => '0',
                    'autofloat'    => '0',
                    'remove_style' => '0',
                    'div2p'        => '0',
                    'autoheight'   => '0',
                    'page'         => '0',
                    'mode'         => '1',
                    'tool'         => '\'bold\', \'italic\', \'underline\'',
                    'mode2'        => '1',
                    'tool2'        => '\'bold\', \'italic\', \'underline\'',
                    'mode3'        => '1',
                    'tool3'        => '\'bold\', \'italic\', \'underline\'',
                    'simpleupload' => '0',
                    'attachment'   => '0',
                    'image_reduce' => '',
                    'image_endstr' => '',
                    'value'        => '',
                    'width'        => '100%',
                    'height'       => '300',
                    'css'          => ''
                ],
                'validate' => $this->validate
            ],
            'related' => [ //关联
                'option'   => [
                    'modul'    => 'news',
                    'title'    => '主题',
                    'limit'    => '20',
                    'pagesize' => '',
                    'css'      => ''
                ],
                'validate' => $this->validate
            ]

        ];

        foreach ( $fields as $t ) {
            // 创建字段
            $f = strtolower( str_replace( ['（', '）', ' ', '(', ')', '/'], '', dr_safe_filename( $t['FieldName'] ) ) );

            if ( $is_tb && ! XR_M()->db->fieldExists( $f, $table ) ) {
                if ( preg_match( '/^f\d+/', $t['FieldName'] ) ) {
                    XR_M()->query( 'ALTER TABLE `' . $table . '` ADD `' . $f . '` Text DEFAULT NULL COMMENT "' . $t['DisplayName'] . '"' );
                } elseif ( strpos( $t['FieldType'], 'char' ) !== false ) {
                    XR_M()->query( 'ALTER TABLE `' . $table . '` ADD `' . $f . '` VARCHAR(512) DEFAULT NULL COMMENT "' . $t['DisplayName'] . '"' );
                } elseif ( strpos( $t['FieldType'], 'int' ) !== false ) {
                    XR_M()->query( 'ALTER TABLE `' . $table . '` ADD `' . $f . '` int(10) DEFAULT NULL COMMENT "' . $t['DisplayName'] . '"' );
                } else {
                    XR_M()->query( 'ALTER TABLE `' . $table . '` ADD `' . $f . '` Text DEFAULT NULL COMMENT "' . $t['DisplayName'] . '"' );
                }
            }

            if ( $t['DisplayType'] == 'image' || $t['DisplayType'] == 'imageex' || $t['DisplayType'] == 'attachment' ) {
                //单文件
                $this->_add_field( $t['DisplayName'], $f, $id, 'File', dr_array2string( $setting['file'] ), $relatedname );
            } elseif ( $t['DisplayType'] == 'editor' || $t['DisplayType'] == 'editormini' ) {
                //编辑器
                $this->_add_field( $t['DisplayName'], $f, $id, 'Editor', dr_array2string( $setting['editor'] ), $relatedname );
            } elseif ( $t['DisplayType'] == 'album' ) {
                //多文件
                $this->_add_field( $t['DisplayName'], $f, $id, 'Files', dr_array2string( $setting['files'] ), $relatedname );
            } elseif ( $t['DisplayType'] == 'relation' ) {
                //相关
                $this->_add_field( $t['DisplayName'], $f, $id, 'Related', dr_array2string( $setting['related'] ), $relatedname );
            } elseif ( $t['DisplayType'] == 'radio' || $t['DisplayType'] == 'select' || $t['DisplayType'] == 'checkbox' ) {
                //单选 多选 下拉
                $options = '';
                $value   = '';
                $o       = explode( PHP_EOL, $t['DisplayValue'] );
                foreach ( $o as $v ) {
                    $p = explode( '|', $v );
                    if ( $p[2] == 1 ) {
                        $value = $p[0];
                    }
                    if ( $p[1] ) {
                        $options .= PHP_EOL . $p[1] . '|' . $p[0];
                    } else {
                        $options .= PHP_EOL . $p[0];
                    }
                }
                $setting['radio']['option']['options'] = trim( $options );
                $setting['radio']['option']['value']   = trim( $value );
                // print_r( dr_array2string($setting['radio']) );exit;
                $this->_add_field( $t['DisplayName'], $f, $id, ucfirst( $t['DisplayType'] ), dr_array2string( $setting['radio'] ), $relatedname );
            } else {
                $fieldtype = ucfirst( $t['DisplayType'] );
                $this->_add_field( $t['DisplayName'], $f, $id, $fieldtype, dr_array2string( ['option' => ['width' => 400]] ), $relatedname );
            }
        }
    }

    /**
     * 其它字段入库
     */
    private function _in_fields( $save, $fields, $row, $id, $mid, $time )
    {

        foreach ( $fields as $t ) {
            if ( $t['DisplayType'] == 'album' ) {
                //相册
                $rt = [
                    'file'        => [],
                    'title'       => [],
                    'description' => []
                ];
                $pics = explode( '@@@', $row[$t['FieldName']] );
                foreach ( $pics as $i => $v ) {
                    $vals = explode( '###', $v );
                    $file = (String) $vals[1];
                    if ( $file && $this->is_down ) {
                        $file = $this->_saveimg( $id, $file, $mid, $time );
                    }
                    $title = trim( (String) $vals[0] );
                    $desc  = trim( (String) $vals[2] );
                    if ( $file || $title || $desc ) {
                        $file                  = $file ? $file : 'nopic'; //相册中无图片文件时加入字符
                        $rt['file'][$i]        = $file;
                        $rt['title'][$i]       = $title;
                        $rt['description'][$i] = $desc;
                    }
                }
                $value = dr_array2string( $rt );
            } elseif ( $t['DisplayType'] == 'checkbox' ) {
                // 多选 relation
                $value = dr_array2string( explode( ',', $row[$t['FieldName']] ) );
            } elseif ( $t['DisplayType'] == 'image' || $t['DisplayType'] == 'imageex' || $t['DisplayType'] == 'attachment' ) {
                //单文件
                $file = (String) $row[$t['FieldName']];
                if ( $file && $this->is_down ) {
                    $file = $this->_saveimg( $id, $file, $mid, $time );
                }
                $value = $file;
            } elseif ( $t['DisplayType'] == 'editormini' || $t['DisplayType'] == 'editor' ) {
                // 编辑器 editor' 'editormini
                $value = $this->_content( $row[$t['FieldName']], $mid, $id, $time );
            } else {
                $value = $row[$t['FieldName']];
            }
            /**字段名称小写 */
            $save[1][strtolower( $t['FieldName'] )] = $value;
        }
        return $save;
    }

    /**
     * 编辑器中的图片转存本地
     */
    private function _content( $content, $mid, $id, $time = '' )
    {

        // $imgs    = dr_get_content_img( $content );
        // 系统取图片函数改成用正则获取
        preg_match_all( '/<img[^>]* src="([^"]*)"[^>]*>/i', $content, $matchs );
        $imgs = $matchs[1];
        // \Phpcmf\Service::L('cache')->set_file('ys_tmp', $imgs);
        foreach ( $imgs as $img ) {
            if ( $img ) {
                $img     = explode( '?', $img )[0];
                $img     = str_replace( SITE_URL, '/', $img );
                $_img    = $this->_saveimg( $id, $img, $mid, $time );
                $content = str_replace( $img, dr_get_file( $_img ), $content );
            }
        }
        return $content;
    }

    private function _db()
    {
        $db = \Config\Database::connect( $this->custom );
        return $db;
    }

    private function _prefix( $table )
    {
        $db = $this->_db();
        return $db->DBPrefix . $table;
    }

    private function _save_content( $mid, $data )
    {
        // 主索引
        $id = $data[1]['id'];
        XR_M()->table( SITE_ID . '_share_index' )->replace(
            [
                'id'  => $id,
                'mid' => $mid
            ]
        );
        // 模块索引
        XR_M()->table( SITE_ID . '_' . $mid . '_index' )->replace(
            [
                'id'        => $id,
                'uid'       => (int) $data[1]['uid'],
                'catid'     => (int) $data[1]['catid'],
                'status'    => (int) $data[1]['status'],
                'inputtime' => (int) $data[1]['inputtime']
            ]
        );
        $data[1]['tableid'] = $tid = floor( $id / 50000 );
        XR_M()->is_data_table( SITE_ID . '_' . $mid . '_data_', $tid );
        XR_M()->table( SITE_ID . '_' . $mid )->replace( $data[1] );
        XR_M()->table( SITE_ID . '_' . $mid . '_data_' . $tid )->replace( $data[0] );

    }

    private function _add_field( $cname, $name, $rid, $fname = 'Text', $setting = '', $relatedname = 'module' )
    {
        // print_r([$cname, $name, $rid, $fname, $setting]);exit;
        if ( XR_M()->db->table( 'field' )
            ->where( 'fieldname', $name )
            ->where( 'relatedid', $rid )
            ->where( 'relatedname', $relatedname )->countAllResults() ) {
            return;
        }

        XR_M()->db->table( 'field' )->insert( array(
            'name'         => $cname,
            'ismain'       => 1,
            'setting'      => $setting,
            'issystem'     => 0,
            'ismember'     => 1,
            'disabled'     => 0,
            'fieldname'    => $name,
            'fieldtype'    => $fname,
            'relatedid'    => $rid,
            'relatedname'  => $relatedname,
            'displayorder' => 10
        ) );
    }

    /**
     * 可用的字段
     */
    private function _fields( $channelmodelid, $lang )
    {

        $attribute = $this->_db()->table( 'attribute' )->where( 'FieldName != ""' )->where( 'ChannelModelID', $channelmodelid )->where( 'IsEnable', 1 )->select( 'FieldName,DisplayName,FieldType,DisplayType,DisplayValue' )->get()->getResultArray(); //字段

        if ( $channelmodelid != 1 ) {
            $channel    = $this->_db()->table( 'channel' )->where( 'ChannelModelID', $channelmodelid )->where( 'LanguageID', $lang )->where( 'IsEnable', 1 )->select( 'ChannelID' )->get()->getResultArray(); //栏目
            $channelids = [];
            foreach ( $channel as $v ) {
                $channelids[] = $v['ChannelID'];
            }
            if (  ! dr_count( $channelids ) ) {
                return false;
            }
        }
        // $where = whereIn( 'ChannelID', $channelids );
        $fields = [];
        foreach ( $attribute as $t ) {
            /**忽略的字段 */
            if (  ! in_array( $t['FieldName'], [
                'InfoID',
                'ChannelID',
                'SpecialID', //所属专题
                'MemberID',
                'InfoTitle',
                'InfoSContent', //内容
                'InfoContent', //简短
                'HasPicture',
                'InfoPicture', //缩略图
                'ReadLevel', //阅读权限
                'HasAttachment',
                'IsLinkUrl',
                'LinkUrl', //转向链接
                'Title',
                'Keywords',
                'Description',
                'InfoOrder',
                'InfoTime',
                'InfoHit',
                'IsEnable', //是否启用
                'IsCheck', //是否审核
                'LanguageID',
                'IsHtml', //是否启用Html静态缓存
                'Html', //静态页面名称

                'ChannelIDEx', //所属扩展频道
                'CityID',
                'DistrictID',
                'TownID',
                'SalesCount',
                'Longitude', //地理坐标
                'Latitude',

                'LabelID', //属性 == 推荐位

                // 'InfoAttachment', // 附件
                // 'InfoAuthor', //作者
                // 'InfoFrom', //来源
                // 'InfoAlbum',    //相册
                // 'InfoRelation', //相关信息
                // 'Tag',          //Tag标签
                // 'InfoPrice',    //本店售价
                // 'TypeID',       //信息类型
                // 'ProvinceID',   //地区
                // 'MarketPrice', //市场价格
                // 'StockCount',    //库存数量
                // 'GivePoint',     //赠送积分
                // 'ExchangePoint', //兑换积分
                // 'Commission', //佣金

                /**频道字段 */
                'Parent', //所属频道
                'ChannelName', //频道名称
                'ChannelModelID', //频道模型
                'IndexTemplate', //频道首页模板
                'ReadTemplate', //频道阅读模板
                'ChannelTarget', //链接目标
                'ChannelSName', //简短名称
                'ChannelOrder',
                'PageSize', //分页条数
                'LinkUrl', //转向链接
                'ReadLevel', //阅读权限
                'IsShow',
                'IsLock',
                'IsEnable',
                'IsHtml',
                'IsSystem',
                'Child',
                'Html',
                'Title',
                'Keywords',
                'Description',
                'ChannelPicture',
                'HasPicture',
                'ChannelSContent',
                'ChannelContent',
                'ChannelStyle'
            ] ) ) {
                if ( $channelmodelid == 1 ) {
                    $count = $this->_db()->table( 'channel' )->where( $t['FieldName'] . ' != ""' )->where( 'LanguageID', $lang )->where( 'IsEnable', 1 )->countAllResults();
                } else {

                    $count = $this->_db()->table( 'info' )->where( $t['FieldName'] . ' != ""' )->whereIn( 'ChannelID', $channelids )->where( 'LanguageID', $lang )->where( 'IsEnable', 1 )->countAllResults();
                }
                $count && $fields[] = $t;
            }

        }
        return $fields;
    }

    // 下载远程文件
    private function _saveimg( $id, $url, $table, $time = '' )
    {

        $timeout = $this->custom['time'] ? $this->custom['time'] : 10;
        if ( stripos( $url, 'file' ) === 0 ) { //忽略
            return $this->save_err( $id, $_url, $table );
        }
        $_url = $url;
        if ( stripos( $url, '//img7' ) === 0 ) {
            $url = str_replace( "//img7", 'http://img6', $url );
        }
        if ( stripos( $url, 'https://img7' ) === 0 ) {
            $url = str_replace( "https://img7", 'http://img6', $url );
        }

        // print_r($url);exit;
        if ( stripos( $url, 'http' ) !== 0 ) {
            $url = $this->old_domain . $url;
        }

        $url = explode( '?', $url )[0];

        if ( defined( 'SYS_ATTACHMENT_CF' ) && SYS_ATTACHMENT_CF ) {
            // 查找附件库 防止重复下载
            $file = dr_catcher_data( $url, $timeout );
            if (  ! $file ) { //超时
                return $this->save_err( $id, $_url, $table );
            }
            $att = XR_M()->table( 'attachment' )->where( 'filemd5', md5( $file ) )->order_by( 'id DESC' )->getRow();

            if ( $att ) {
                return $att['id'];
            }
        }

        // 下载远程文件
        $attconfig = XR_M( 'Attachment' )->get_attach_info(
            intval( $this->module['field']['thumb']['setting']['option']['attachment'] ), $this->module['field']['thumb']['setting']['option']['image_reduce']
        );

        $rt = XR_L( 'Upload' )->down_file( [
            'url'        => $url,
            'path'       => $time,
            'timeout'    => $timeout,
            'watermark'  => 0,
            'attachment' => $attconfig
            // 'file_ext'   => $ext,

        ] );
        // print_r( [WEBPATH,CONFIGPATH,$url,$ext,$att, $attconfig,$rt ] );exit;
        if ( $rt['code'] ) {
            // 附件归档
            $att = XR_M( 'Attachment' )->save_data( $rt['data'], XR_M()->dbprefix( SITE_ID . '_' . $table ) . '-' . $id, 1 );
            if ( $att['code'] ) {
                return $att['code'];
            }
        }
        return $this->save_err( $id, $_url, $table );
    }

    public function save_err( $id, $url, $table )
    {
        //下载失败返回原文件名并记录

        $failimgs            = [];
        $ys_youdian_failimgs = XR_L( 'cache' )->get_file( 'ys_youdian_failimgs' );
        if ( is_array( $ys_youdian_failimgs ) ) {
            $failimgs = $ys_youdian_failimgs;
        }
        if ( $table == 'share_category' ) {
            $name = '共享栏目';
        } else {
            $c    = array_search( $table, array_column( $this->mods, 'nid' ) );
            $name = $c !== false ? $this->mods[$c]['typename'] : '未知'; //模型英文名称
        }
        $failimgs[] = [
            'url'    => $url,
            'name'   => $name,
            'id'     => $id,
            'siteid' => SITE_ID
        ];
        XR_L( 'cache' )->set_file( 'ys_youdian_failimgs', $failimgs );
        return $url;
    }

    /**
     * 将特定的HTML实体转换为它们对应的字符。
     *
     * @param string $content 包含HTML实体的字符串。
     * @return string 替换HTML实体后的字符串。
     */
    private function special_html( $content )
    {
        $content = str_replace( "&lt;", "<", $content );
        $content = str_replace( "&gt;", ">", $content );
        $content = str_replace( "&amp;ldquo;", "“", $content );
        $content = str_replace( "&amp;rdquo;", "”", $content );
        $content = str_replace( "&ldquo;", "“", $content );
        $content = str_replace( "&rdquo;", "”", $content );
        $content = str_replace( "&quot;", '"', $content );
        $content = str_replace( "&amp;mdash;", '—', $content );
        $content = str_replace( "&mdash;", '—', $content );
        return $content;
    }
}
