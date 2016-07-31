<?php
class View
{
    /**
     * 模板文件路径
     * @var string
     */
    private $viewPath;

    /**
     * 编译文件保存路径
     * @var string
     */
    private $cachePath;

    /**
     * 模板文件后缀名
     * @var string
     */
    private $viewFileSuffix = ['.html','.php'];

    /**
     * 保存渲染的变量
     * @var array
     */
    private $vars = [];

    /**
     * 保存模板缓冲
     * @var string
     */
    private $viewFileBuffer;

    /**
     * 模板真实路径
     * @var string
     */
    private $viewFileRealPath;

    /**
     * 编译文件路径
     * @var string
     */
    private $compileFilePath;

    /**
     * 保存yield section对应关系
     * @var array
     */
    private $sections = [];

    /**
     * 替换规则
     * @var array
     */
    private $replaces = [
        "/{{\s*(.*?)\s*}}/" => '<?php echo $this->e(${1}); ?>',
        "/{!!\s*(.*?)\s*!!}/" => '<?php echo ${1}; ?>',
        "/{{--\s*(.*?)\s*--}}/" => '',
        "/@if\((.*?)\)/" => '<?php if(${1}): ?>',
        "/@elseif\((.*?)\)/" => '<?php elseif(${1}): ?>',
        "/@else/" => '<?php else: ?>',
        "/@endif/" => '<?php endif; ?>',
        "/@foreach\((.*?)\)/" => '<?php foreach(${1}): ?>',
        "/@endforeach/" => '<?php endforeach; ?>',
    ];

    /**
     * 编译文件缓存时间
     * @var int
     */
    private $cacheExpire = 0;

    /**
     * View 构造函数
     * @param $viewPath Template File Path
     * @param $cachePath Compile File Cache Path
     */
    public function __construct($viewPath, $cachePath)
    {
        $this->viewPath = $viewPath;
        $this->cachePath = $cachePath;
    }

    /**
     * 分配渲染变量
     * @param $key
     * @param $value
     * @return $this
     */
    public function with($key, $value)
    {
        $this->vars[$key] = $value;
        return $this;
    }

    /**
     * 初始化模板信息
     * @param $fileName
     * @return $this
     */
    public function make($fileName)
    {
        $this->viewFileRealPath = $this->getViewFileRealPath($fileName);
        $this->compileFilePath = $this->getCompileFilePath($this->viewFileRealPath);

        if ($this->isExpireOut()) {
            $this->setViewFileBuffer($fileName);
        }

        return $this;
    }

    /**
     * 渲染
     * @return string
     */
    public function render()
    {
        $this->compile();
        extract($this->vars);

        ob_start();
        require $this->compileFilePath;
        return ob_get_clean();
    }

    /**
     * 添加模板文件后缀
     * @param $suffix
     * @return int
     */
    public function addViewFileSuffix($suffix)
    {
        return array_unshift($this->viewFileSuffix, $suffix);
    }

    /**
     * 添加规则
     * @param $preg
     * @param $relacement
     */
    public function addRule($preg, $relacement)
    {
        $this->replaces[$preg] = $relacement;
    }

    /**
     * 设置编译缓存时间
     * @param $second
     */
    public function setCacheExpire($second)
    {
        $this->cacheExpire = $second;
    }

    /**
     * 判断缓存是否过期
     * @return bool
     */
    private function isExpireOut()
    {
        if (is_file($this->compileFilePath)) {
            $createTime = filectime($this->compileFilePath);
            if (($createTime + $this->cacheExpire) < time()) {
                return true;
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * 设置文件缓冲
     * @param $fileName
     */
    private function setViewFileBuffer($fileName)
    {
        $buffer = $this->dealExtends($fileName);
        $buffer = $this->dealYield($buffer);
        $buffer = $this->dealInclude($buffer);
        while(1) {
            preg_match("/(@yield|@include)/", $buffer, $matches);
            if (! empty($matches)) {
                $buffer = $this->dealYield($buffer);
                $buffer = $this->dealInclude($buffer);
            } else {
                break;
            }
        }
        $buffer = $this->dealSection($buffer);

        $this->viewFileBuffer = $buffer;
    }

    /**
     * 处理section的继承
     * @param $buffer
     * @return mixed
     */
    private function dealSection($buffer)
    {
        $result = preg_replace_callback("/@section\(('|\")(.*?)('|\")\)([\s\S]*)@stop/", function($matches) {
            return $this->sections[$matches[2]];
        }, $buffer);

        return $result;
    }

    /**
     * 用section内容替换yield处
     * @param $buffer
     * @return mixed
     */
    private function dealYield($buffer)
    {
        $result = preg_replace_callback("/@yield\(('|\")(.*?)('|\")\)/", function($matches) {
            if (!empty($this->sections[$matches[2]])) {
                return $this->sections[$matches[2]];
            }
            return '';
        }, $buffer);

        return $result;
    }

    /**
     * 处理继承关系
     * @param $fileName
     * @return string
     */
    private function dealExtends($fileName)
    {
        $buffer = file_get_contents($this->getViewFileRealPath($fileName));

        //匹配section
        preg_match_all("/@section\(['|\"](.*?)['|\"]\)([\s\S]*?)@stop/", $buffer, $matchSection);
        if (!empty($matchSection[0])) {
            foreach ($matchSection[1] as $key => $value) {
                if (empty($this->sections[$value])) {
                    $this->sections[$value] = $matchSection[2][$key];
                }
            }
        }

        //继承匹配
        preg_match("/@extends\(('|\")(.*?)('|\")\)/", $buffer, $matchExtends);
        if (!empty($matchExtends)) {
            return $this->dealExtends($matchExtends[2]);
        }

        return $buffer;
    }

    /**
     * 获得需要引入的文件
     * @param $buffer
     * @return bool
     */
    private function dealInclude($buffer)
    {
        //判断是否有引入文件
        $result = preg_replace_callback("/@include\(('|\")(.*?)('|\")\)/", function($matches) {
            $bufferTemp = file_get_contents($this->getViewFileRealPath($matches[2]));
            //递归查找引入文件
            return $this->dealInclude($bufferTemp);
        }, $buffer);

        return $result;
    }

    /**
     * 获得模板文件完整地址
     * @param $fileName
     * @return string
     * @throws Exception
     */
    private function getViewFileRealPath($fileName)
    {
        $fileName = str_replace(".", DIRECTORY_SEPARATOR, $fileName);

        foreach ($this->viewFileSuffix as $suffix) {
            $viewRealPath = $this->viewPath . DIRECTORY_SEPARATOR . $fileName . $suffix;
            if (is_file($viewRealPath)) {
                return $viewRealPath;
            }
        }

        throw new Exception($fileName . " not found");
    }

    /**
     * 获得编译文件的完整名
     * @return string
     */
    private function getCompileFilePath($viewFileRealPath)
    {
        return $this->cachePath . DIRECTORY_SEPARATOR . md5($viewFileRealPath);
    }

    /**
     * 编译
     * @return int
     */
    private function compile()
    {
        if ($this->isExpireOut()) {
            //执行编译替换
            $buffer = preg_replace(array_keys($this->replaces), array_values($this->replaces), $this->viewFileBuffer);

            return file_put_contents($this->compileFilePath, $buffer, LOCK_EX);
        }
    }

    /**
     * 安全输出变量
     * @param $value
     * @return string
     */
    private function e($value)
    {
        return htmlspecialchars($value);
    }

    /**
     * 驼峰转蛇形
     * @param $str
     * @return string
     */
    private function snakeCase($str)
    {
        $array = array();
        $len = strlen($str);
        for($i = 0; $i < $len; $i++){
            if($str[$i] == strtolower($str[$i])){
                $array[] = $str[$i];
            }else{
                if($i>0){
                    $array[] = '_';
                }
                $array[] = strtolower($str[$i]);
            }
        }

        $result = implode('',$array);
        return $result;
    }

    /**
     * with变量
     * @param $method
     * @param $parameters
     * @return View
     */
    public function __call($method, $parameters)
    {
        if(substr($method,0,4) == 'with') {
            return $this->with($this->snakeCase(substr($method, 4)), $parameters[0]);
        }

        throw new BadMethodCallException("Method $method not found");
    }
}
