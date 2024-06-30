<?php
/**
 * 菜单配置
 */
return [
  'admin' => [
    'app' => [
      'left' => [
        // 集成单个菜单
        'app-plugin' => [
          'link' => [
            'app-Ys_youdian' => [
              'name' => 'YouDianCMS数据转换',
              'icon' => 'fa fa-database',
              'uri' => 'Ys_youdian/home/index',
            ],
          ]
        ],
      ],
    ],
  ],
];