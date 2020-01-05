<?php
/*
FillAmeaPixel NetWork MiniSqlAPI
调用请保留此备注

2020 1 15 更新
*/




namespace FGamePixel\Function\libs;


use FPixelGame\Function\Group;

class MiniSqlApi
{
    private $plugin;

    private $ip = "127.0.0.1";
    private $port = 18188;

    public function __construct(ChatMain $plugin)
    {
        $this->plugin = $plugin;
    }

    // 这个方法不要改动
    public function accessMiniSql(string $message): string {
        // 创建套接字
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($socket, $this->ip, $this->port);
        // 发送数据
        socket_write($socket, $message, strlen($message));
        // 接收服务器发来的数据
        $string = socket_read($socket, 8190);
        // 关闭套接字
        socket_close($socket);
        // 访问错误后自我关闭
        if(in_array($string, ["[MiniSql] grammar error", "[MiniSql] unknown command", "[MiniSql] invalid session"])){
            $this->plugin->getLogger()->info($string . ": " . $message);
            $this->plugin->getServer()->getPluginManager()->disablePlugin($this->plugin);
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
            $this->plugin->getLogger()->info("Error: 无法在 MiniSql 里找到配置: " . $config_path);
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
        $data = yaml_parse(preg_replace("#^([ ]*)([a-zA-Z_]{1}[ ]*)\\:$#m", "$1\"$2\":", $data));
        return $data;
    }

    public function unsetData(string $config_path, string $pointer): bool {
        return ($this->accessMiniSql("unset " . $config_path . " " . $pointer) === "true");
    }
}