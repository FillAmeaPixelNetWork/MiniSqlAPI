# FMiNniSqlAPI
PocketMine的群组转发器前置API,字符串虚拟数据库

## 警告
调用API请保存许可和备注

# 版本
更新记录: 2.3

# 原理
API创建一个虚拟数据库作为转发器,插件向转发器发送数据包,接收,转发,处理


# API事例
##Plugin
```php

    public function getAccessIp(): string {
        return $this->getPluginConfig()->get("SqlAccessIp");
    }

    public function getAccessPort(): int {
        return (int) $this->getPluginConfig()->get("SqlAccessPort");
    }

    public function accessMiniSql(string $message): string {
        // 创建套接字
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($socket, $this->getAccessIp(), $this->getAccessPort());
        // 发送数据
        socket_write($socket, $message, strlen($message));
        // 接收服务器发来的数据
        $string = socket_read($socket, 8190);
        // 关闭套接字
        socket_close($socket);
        // 访问错误后自我关闭
        if(in_array($string, ["[MiniSql] grammar error", "[MiniSql] unknown command", "[MiniSql] invalid session"])){
            $this->getLogger()->info($string . ": " . $message);
            $this->getPluginLoader()->disablePlugin($this);
        }
        return $string;
    }

    public function createConfig(string $config_path, array $default = null): bool {
        $command = "new " . $config_path;
        if($default !== null){
            $content = yaml_emit($default, YAML_UTF8_ENCODING);
            $command .= " " . $content;
        }
        return ($this->accessMiniSql($command) === "true");
    }

    public function getConfigData(string $config_path): array {
        $data = $this->accessMiniSql("get_all " . $config_path);
        if($data === "false"){
            $this->getLogger()->info("Error: 无法在 MiniSql 里找到配置: " . $config_path);
            return [];
        }else{
            return yaml_parse(preg_replace("#^([ ]*)([a-zA-Z_]{1}[ ]*)\\:$#m", "$1\"$2\":", $data));
        }
    }

    public function existsConfig(string $config_path): bool {
        return ($this->accessMiniSql("exists " . $config_path) === "true");
    }

    public function removeConfig(string $config_path): bool {
        return ($this->accessMiniSql("del " . $config_path) === "true");
    }

    public function setData(string $config_path, string $pointer, $data): bool {
        $is_array = is_array($data);
        $command = (($is_array)? "set_array": "set") . " " . $config_path . " " . $pointer;
        $command .= (" " . (($is_array)? yaml_emit($data, YAML_UTF8_ENCODING): $data));
        return ($this->accessMiniSql($command) === "true");
    }

    public function issetData(string $config_path, string $pointer): bool {
        return ($this->accessMiniSql("isset " . $config_path . " " . $pointer) === "true");
    }

    public function getData(string $config_path, string $pointer) {
        $data = $this->accessMiniSql("get " . $config_path . " " . $pointer);
        return $data;
    }

    public function getDataOfArray(string $config_path, string $pointer) {
        $data = $this->accessMiniSql("get " . $config_path . " " . $pointer);
        $data = yaml_parse(preg_replace("#^([ ]*)([a-zA-Z_]{1}[ ]*)\\:$#m", "$1\"$2\":", $data));
        return $data;
    }

    public function unsetData(string $config_path, string $pointer): bool {
        return ($this->accessMiniSql("unset " . $config_path . " " . $pointer) === "true");
    }

    /**
     * @param Position $position
     * @return string
     */
    public static function toString(Position $position): string {
        return $position->x . ":" . $position->y . ":" . $position->z . ":" . $position->getLevel()->getFolderName();
    }

    /**
     * @param string $string
     * @return Position
     */
    public static function toPosition(string $string){
        $strArray = explode(":", $string);
        $level = Server::getInstance()->getLevelByName($strArray[3]);
        return (new Position((double)$strArray[0], (double)$strArray[1], (double)$strArray[2], $level));
    }
}

```
##SqlServer
###Made SQL
```php
    public static function createConfig(string $config_path, string $default): string {
        if(self::existsConfig($config_path) === "false"){
            if($default === "[]"){
                $default = [];
            }else{
                $default = yaml_parse(preg_replace("#^([ ]*)([a-zA-Z_]{1}[ ]*)\\:$#m", "$1\"$2\":", $default));
            }
            $temp = explode("\\", $config_path);
            $path = self::DATA_FOLDER;
            for($number = 0; $number < count($temp) - 1; $number ++){
                $path .= "\\" . $temp[$number];
                @mkdir($path);
            }
            $config = new Config(self::DATA_FOLDER . "\\" . $config_path, Config::YAML, $default);
            $config->save();
            return "true";
        }else{
            return "false";
        }
    }

    public static function getConfigData(string $config_path): string {
        if(self::existsConfig($config_path) === "true"){
            $array = (new Config(self::DATA_FOLDER . "\\" . $config_path, Config::YAML))->getAll();
            return yaml_emit($array, YAML_UTF8_ENCODING);
        }else{
            return "false";
        }
    }

    public static function existsConfig(string $config_path): string {
        return is_file(self::DATA_FOLDER . "\\" . $config_path)? "true": "false";
    }

    public static function removeConfig(string $config_path): string {
        if(self::existsConfig($config_path) === "true"){
            unlink(self::DATA_FOLDER . "\\" . $config_path);
            return "true";
        }else{
            return "false";
        }
    }

    public static function setData(string $config_path, string $pointer, string $data, bool $is_array = false): string {
        if(self::existsConfig($config_path) === "true"){
            if(self::issetData($config_path, $pointer) === "true"){
                self::unsetData($config_path, $pointer);
            }
            if($is_array){
                $data = yaml_parse(preg_replace("#^([ ]*)([a-zA-Z_]{1}[ ]*)\\:$#m", "$1\"$2\":", $data));
            }
            $config = new Config(self::DATA_FOLDER . "\\" . $config_path, Config::YAML);
            $temp = $config->getAll();
            $pointers = explode("->", $pointer);
            $value1 = [];
            $counts = count($pointers);
            for($count = 0; $count < $counts; $count ++) {
                $key = $pointers[($counts - 1) - $count];
                if ($key === $pointers[($counts - 1)]) {
                    $value1 = [$key => $data];
                } else{
                    $value1 = [$key => $value1];
                }
            }
            $value = array_merge_recursive($value1, $temp);
            $config->setAll($value);
            $config->save();
            return "true";
        }else{
            return "false";
        }
    }

    public static function issetData(string $config_path, string $pointer): string {
        return (self::getData($config_path, $pointer) === "false")? "false": "true";
    }

    public static function getData(string $config_path, string $pointer): string {
        if(self::existsConfig($config_path) === "true"){
            $config = new Config(self::DATA_FOLDER . "\\" . $config_path, Config::YAML);
            $value = $config->getAll();
            $pointers = explode("->", $pointer);
            for($count = 0; $count < count($pointers); $count ++){
                $key = $pointers[$count];
                if(isset($value[$key])){
                    $value = $value[$key];
                }else{
                    return "false";
                }
            }
            if(is_array($value)){
                $value = yaml_emit($value, YAML_UTF8_ENCODING);
            }
            return $value;
        }else{
            return "false";
        }
    }

    public static function unsetData(string $config_path, string $pointer): string {
        if(self::issetData($config_path, $pointer) === "true"){
            $config = new Config(self::DATA_FOLDER . "\\" . $config_path, Config::YAML);
            $temp = $config->getAll();
            $pointers = explode("->", $pointer);

            if($pointer === $pointers[0]){
                if(isset($temp[$pointer])){
                    unset($temp[$pointer]);
                }
            }else{
                $value = [];
                $value1 = null;
                $counts = (count($pointers) - 1);
                $number = 0;
                foreach ($pointers as $count => $key){
                    if($count != $counts){
                        $value[$number] = ($number == 0)? $temp[$key]: $value1[$key];
                        $value1 = $value[$number];
                        $number += 1;
                    }
                }
                for($count = 0; $count < count($value); $count ++) {
                    $number = (count($value) - 1) - $count;
                    if($number == (count($value) - 1)){
                        unset($value[$number][$pointers[$counts]]);
                    }else{
                        $value[$number][$pointers[$number + 1]] = $value[$number + 1];
                    }
                }
                $temp[$pointers[0]] = $value[0];
            }

            $config->setAll($temp);
            $config->save();
            return "true";
        }else{
            return "false";
        }
    }
}
```
###接收
```php
    private $serverSocket;
    public $clientTool = [];

    public function __construct($serverSocket)
    {
        $this->serverSocket = $serverSocket;
    }

    public function run(){
        while (true){
            // 阻塞式方法socket_accept
            if(($client = socket_accept($this->serverSocket)) !== false){

                @socket_getpeername($client, $address, $port);
                //echo "[MiniSql] 收到来自IP: " . $address . ":" . $port . " 的请求 ......\n\r";

                $bufferIn = socket_read($client, 8192);
                $args = explode(" ", $bufferIn);
                $command = array_shift($args);

                $string = $this->execute($command, $args);
                socket_write($client, $string, strlen($string));
                socket_close($client);
                //echo "[MiniSql] 来自IP: " . $address . ":" . $port . " 的请求处理完毕 ......\n\r";
            }
        }
    }

    public function execute(string $command, array $args): string {
        if(array_key_exists(0, $args)){
            $config_path = $args[0];
            if(in_array($command, ["isset", "get", "unset"]) and !array_key_exists(1, $args)){
                return "[MiniSql] grammar error";
            }
            switch ($command){
                case "exists":
                    return PluginMain::existsConfig($config_path);
                case "new":
                    $default = "[]";
                    if(array_key_exists(1, $args)){
                        $value = $args;
                        array_shift($value);
                        $default = implode(" ", $value);
                    }
                    return PluginMain::createConfig($config_path, $default);
                case "get_all":
                    return PluginMain::getConfigData($config_path);
                case "del":
                    return PluginMain::removeConfig($config_path);
                case "set":
                case "set_array":
                    if(array_key_exists(2, $args)){
                        $set_value = $args;
                        array_shift($set_value);
                        array_shift($set_value);
                        $data = implode(" ", $set_value);
                        return PluginMain::setData($config_path, $args[1], $data, $command === "set_array");
                    }else{
                        return "[MiniSql] grammar error";
                    }
                case "isset":
                    return PluginMain::issetData($config_path, $args[1]);
                case "get":
                    return PluginMain::getData($config_path, $args[1]);
                case "unset":
                    return PluginMain::unsetData($config_path, $args[1]);
                default:
                    return "[MiniSql] unknown command";
            }
        }else{
            return "[MiniSql] invalid session";
        }
    }

    /**
     * @return string
     */
    public function getThreadName(){
        return "MiniSql Server Thread";
    }

    public function setGarbage()
    {
        // TODO: Implement setGarbage() method.
    }
}
```
#怎么在FGamePixel核心里面调用?
使用use FPixelGame\Function\libs\MiniSqlAPI;

##联系我们
#office@fapixel.com
##正在开发FPixelGame核心,全新PocketMine,来试试?
