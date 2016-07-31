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
    
#### @yield @section
    和laravel中使用一致
  
#### 设置编译缓存时间
    $view->setCacheExpire(3600); //默认为0
    
#### 添加自定义规则
    $view->addRule("/@time\((.*?)\)/", '<?php echo date("Y-m-d", ${1});?>');
    //在模板中使用
    @time($value)
    
