<?php
class View
{
    /**
     * 模板文件路径
     * @var string
     */
    protected $viewPath;

    /**
     * 编译文件保存路径
     * @var string
     */
    protected $cachePath;

    /**
     * 模板文件后缀名
     * @var string
     */
    protected $viewFileSuffix = ['.html','.php'];

    /**
     * 保存渲染的变量
     * @var array
     */
    protected $vars = [];

    /**
     * 当前模板真实路径
     * @var string
     */
    protected $viewFileRealPath;

    /**
     * 当前编译文件路径
     * @var string
     */
    protected $compileFilePath;

    /**
     * 保存yield section对应关系
     * @var array
     */
    protected $sections = [];

    /**
     * section堆
     * @var array
     */
    protected $sectionStack = [];

    /**
     * 记录编译次数
     * @var int
     */
    protected $compileCount = 0;

    /**
     * 替换规则
     * @var array
     */
    protected $rules = [
        "/{{\s*(.*?)\s*}}/" => '<?php echo $this->e(${1}); ?>',
        "/{!!\s*(.*?)\s*!!}/" => '<?php echo ${1}; ?>',
        "/{{--\s*(.*?)\s*--}}/" => '<?php /* ${1} */ ?>',
        "/@if\((.*?)\)/" => '<?php if(${1}): ?>',
        "/@elseif\((.*?)\)/" => '<?php elseif(${1}): ?>',
        "/@else/" => '<?php else: ?>',
        "/@endif/" => '<?php endif; ?>',
        "/@foreach\((.*?)\)/" => '<?php foreach(${1}): ?>',
        "/@endforeach/" => '<?php endforeach; ?>',
    ];

    /**
     * View 构造函数
     * @param $viewPath
     * @param $cachePath
     */
    public function __construct($viewPath, $cachePath)
    {
        $this->viewPath = $viewPath;
        $this->cachePath = $cachePath;
    }

    /**
     * 使用模板
     * @param $file
     * @param array $vars
     * @return $this
     */
    public function make($file, $vars = [])
    {
        //保存分配变量
        $this->vars = array_merge($this->vars, $vars);
        //获得文件真实路径
        $this->viewFileRealPath = $this->getViewFileRealPath($file);
        //获得编译文件路径
        $this->compileFilePath = $this->getCompileFilePath($this->viewFileRealPath);

        return $this;
    }

    /**
     * 渲染
     * @return string
     */
    public function render()
    {
        //进行编译
        $this->compile();
        //解析变量
        extract($this->vars);

        if ($this->compileCount == 0) {
            ob_start();
        }
        $this->compileCount++;

        require $this->compileFilePath;

        if ($this->compileCount == 1) {
            $result = ob_get_clean();
        }
        return isset($result) ? $result : '';
    }

    /**
     * 判断编译文件是否过期
     * @return bool
     */
    protected function isExpired()
    {
        if (! is_file($this->compileFilePath)) {
            return true;
        }

        $cacheLastModify = filemtime($this->compileFilePath);
        $viewLastModify = filemtime($this->viewFileRealPath);

        if ($cacheLastModify <= $viewLastModify) {
            return true;
        }

        return false;
    }

    /**
     * 编译模板
     */
    protected function compile()
    {
        if($this->isExpired()) {
            $buffer = file_get_contents($this->viewFileRealPath);
            $buffer = $this->compileExtends($buffer);
            $buffer = $this->compileInclude($buffer);
            $buffer = $this->compileSection($buffer);
            $buffer = $this->compileYield($buffer);
            $buffer = $this->compileEcho($buffer);
            $this->putBufferToCompileFile($buffer);
        }
    }

    /**
     * 编译extends
     * @param $buffer
     * @return string
     */
    protected function compileExtends($buffer)
    {
        $pattern = "/@extends\(['|\"](.+?)['|\"]\)/";

        preg_match_all($pattern, $buffer, $matches);

        if(!empty($matches[0])) {
            foreach ($matches[1] as $value) {
                $buffer .= PHP_EOL .'<?php $this->make("'. $value .'",$this->vars)->render();?>';
            }
        }

        $buffer = preg_replace($pattern, '', $buffer);

        return trim($buffer);
    }

    /**
     * 编译include
     * @param $buffer
     * @return string
     */
    protected function compileInclude($buffer)
    {
        $pattern = "/@include\(['|\"](.+?)['|\"]\)/";

        $buffer = preg_replace_callback($pattern, function($matches){
            return '<?php $this->make("'. $matches[1] .'",$this->vars)->render(); ?>';
        }, $buffer);

        return trim($buffer);
    }

    /**
     * 编译section
     * @param $buffer
     * @return string
     */
    protected function compileSection($buffer)
    {
        $pattern = "/@section\(['|\"](.+?)['|\"]\)([\s\S]+?)@(stop|show)/";
        $buffer = preg_replace_callback($pattern, function($matches){
            $str = PHP_EOL . '<?php $this->startSection("'. $matches[1] .'");?>'
                . PHP_EOL
                . $matches[2]
                . PHP_EOL;
            if ($matches[3] == 'show') {
                $str .= '<?php $this->stopSection(true); ?>' . PHP_EOL;
            } else {
                $str .= '<?php $this->stopSection(); ?>' . PHP_EOL;
            }
            return $str;
        }, $buffer);

        return trim($buffer);
    }

    /**
     * 开始section
     * @param $name
     */
    protected function startSection($name)
    {
        if(ob_start()) {
            $this->sectionStack[] = $name;
        }
    }

    /**
     * 结束section
     * @param bool $show
     */
    protected function stopSection($show = false)
    {
        $buffer = ob_get_clean();

        $last = array_pop($this->sectionStack);

        if(!isset($this->sections[$last])) {
            if ($show) {
                echo $buffer;
            } else {
                $this->sections[$last] = $buffer;
            }
        } else {
            echo $this->sections[$last];
        }
    }

    /**
     * 编译yield
     * @param $buffer
     * @return string
     */
    protected function compileYield($buffer)
    {
        $pattern = "/@yield\(['|\"](.+?)['|\"]\)/";
        $buffer = preg_replace_callback($pattern, function($matches) {
            if (isset($this->sections[$matches[1]])) {
                return $this->sections[$matches[1]];
            } else {
                return '';
            }
        }, $buffer);

        return trim($buffer);
    }

    /**
     * 编译输出
     * @param $buffer
     * @return mixed
     */
    protected function compileEcho($buffer)
    {
        $buffer = preg_replace(array_keys($this->rules), array_values($this->rules), $buffer);
        return $buffer;
    }

    /**
     * 将缓冲内容放到编译文件中
     * @param $buffer
     * @return int
     */
    protected function putBufferToCompileFile($buffer)
    {
        return file_put_contents($this->compileFilePath, $buffer, LOCK_EX);
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
     * 安全输出变量
     * @param $value
     * @return string
     */
    protected function e($value)
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
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
     * 添加模板后缀名
     * @param $suffix
     */
    public function addViewFileSuffix($suffix)
    {
        array_unshift($this->viewFileSuffix, $suffix);
    }

    /**
     * 添加自定义规则
     * @param $rule
     * @param $replace
     */
    public function addRule($rule, $replace)
    {
        $this->rules[$rule] = $replace;
    }

    /**
     * 驼峰转蛇形
     * @param $str
     * @return string
     */
    protected function snakeCase($str)
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
