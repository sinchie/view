# php模板引擎

#### 基本使用

    $viewPath = "模板文件目录";
    $cachePath = "放置编译缓存目录";
    $view = new View($viewPath, $cachePath);
    
    $view->make('模板名')->with('key','value')->withKey('value');
    $view->render();
    
#### 引入文件
    @include('file.path');
    
#### 继承模板
    @extends('file.path');
    
#### @yield
    //在主模板中
    <title>@yield('name')</title>
    //在继承模板中填充它
    @section(name)
        i am title
    @stop
    
#### @section
    @section 标签有两种结束标签 @show @stop
    @stop 用来填充@yield
    @show 类似@yield相当于@yield有了默认内容，如果在继承的模板中没有为这个section填充内容，那么将显示默认内容
  
#### 编译缓存
    当模板文件有更新，编译文件自动更新。
    
#### 添加自定义规则
    $view->addRule("/@time\((.*?)\)/", '<?php echo date("Y-m-d", ${1});?>');
    //在模板中使用
    @time($value)
    
#### 添加模板后缀名
    $view->addViewFileSuffix('.tpl'); //默认支持.html .php
    
