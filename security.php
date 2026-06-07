<?php
// 网站安全防护配置 - DDoS/CC攻击防护

class SecurityProtection {
    private static $instance = null;
    private $rateLimitFile = 'security/rate_limit.log';
    private $ipBlacklistFile = 'security/ip_blacklist.txt';
    private $config;
    
    public function __construct() {
        $this->config = [
            'rate_limit' => [
                'requests_per_minute' => 60,    // 每分钟最大请求数
                'requests_per_hour' => 300,     // 每小时最大请求数
                'ban_threshold' => 10,          // 触发封禁的阈值
                'ban_duration' => 3600          // 封禁时长（秒）
            ],
            'user_agent_check' => true,
            'referer_check' => true,
            'geo_block' => [
                'enabled' => false,
                'allowed_countries' => ['CN']   // 允许的国家代码
            ]
        ];
        
        $this->initSecurityDir();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initSecurityDir() {
        if (!file_exists('security')) {
            mkdir('security', 0755, true);
        }
    }
    
    // 获取客户端真实IP
    public function getClientIP() {
        $ip = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // 验证IP格式
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    // 速率限制检查
    public function checkRateLimit() {
        $ip = $this->getClientIP();
        
        // 检查黑名单
        if ($this->isIPBlacklisted($ip)) {
            $this->logAttack('BLACKLISTED_ACCESS', $ip);
            $this->sendRateLimitResponse();
        }
        
        $currentTime = time();
        $rateData = $this->loadRateData();
        
        if (!isset($rateData[$ip])) {
            $rateData[$ip] = [
                'minute' => ['count' => 1, 'timestamp' => $currentTime],
                'hour' => ['count' => 1, 'timestamp' => $currentTime],
                'violations' => 0
            ];
        } else {
            // 检查分钟级限制
            if ($currentTime - $rateData[$ip]['minute']['timestamp'] < 60) {
                $rateData[$ip]['minute']['count']++;
            } else {
                $rateData[$ip]['minute'] = ['count' => 1, 'timestamp' => $currentTime];
            }
            
            // 检查小时级限制
            if ($currentTime - $rateData[$ip]['hour']['timestamp'] < 3600) {
                $rateData[$ip]['hour']['count']++;
            } else {
                $rateData[$ip]['hour'] = ['count' => 1, 'timestamp' => $currentTime];
            }
        }
        
        // 检查是否超过限制
        $minuteExceeded = $rateData[$ip]['minute']['count'] > $this->config['rate_limit']['requests_per_minute'];
        $hourExceeded = $rateData[$ip]['hour']['count'] > $this->config['rate_limit']['requests_per_hour'];
        
        if ($minuteExceeded || $hourExceeded) {
            $rateData[$ip]['violations']++;
            
            if ($rateData[$ip]['violations'] >= $this->config['rate_limit']['ban_threshold']) {
                $this->addToBlacklist($ip);
                $this->logAttack('RATE_LIMIT_BAN', $ip);
            } else {
                $this->logAttack('RATE_LIMIT_EXCEEDED', $ip);
            }
            
            $this->saveRateData($rateData);
            $this->sendRateLimitResponse();
        }
        
        $this->saveRateData($rateData);
        return true;
    }
    
    // 用户代理检查
    public function checkUserAgent() {
        if (!$this->config['user_agent_check']) return true;
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // 检查常见攻击工具的用户代理
        $maliciousAgents = [
            'sqlmap', 'nikto', 'nmap', 'metasploit', 'wget', 'curl',
            'python-requests', 'go-http-client', 'java', 'scrapy'
        ];
        
        foreach ($maliciousAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                $this->logAttack('MALICIOUS_USER_AGENT', $this->getClientIP(), $userAgent);
                $this->sendForbiddenResponse();
            }
        }
        
        return true;
    }
    
    // 请求头检查
    public function checkHeaders() {
        // 检查常见的攻击头信息
        $suspiciousHeaders = [
            'X-Forwarded-For' => '/^\d+\.\d+\.\d+\.\d+.*,.*/'
        ];
        
        foreach ($suspiciousHeaders as $header => $pattern) {
            if (isset($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $header))])) {
                $value = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $header))];
                if (preg_match($pattern, $value)) {
                    $this->logAttack('SUSPICIOUS_HEADER', $this->getClientIP(), $header . ': ' . $value);
                    $this->sendForbiddenResponse();
                }
            }
        }
        
        return true;
    }
    
    // IP黑名单管理
    private function isIPBlacklisted($ip) {
        if (!file_exists($this->ipBlacklistFile)) return false;
        
        $blacklist = file($this->ipBlacklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return in_array($ip, $blacklist);
    }
    
    private function addToBlacklist($ip) {
        if (!$this->isIPBlacklisted($ip)) {
            file_put_contents($this->ipBlacklistFile, $ip . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
    
    // 数据存储和加载
    private function loadRateData() {
        if (!file_exists($this->rateLimitFile)) return [];
        
        $data = file_get_contents($this->rateLimitFile);
        $rateData = json_decode($data, true) ?? [];
        
        // 清理过期数据（超过1小时）
        $currentTime = time();
        foreach ($rateData as $ip => $data) {
            if ($currentTime - max($data['minute']['timestamp'], $data['hour']['timestamp']) > 3600) {
                unset($rateData[$ip]);
            }
        }
        
        return $rateData;
    }
    
    private function saveRateData($data) {
        file_put_contents($this->rateLimitFile, json_encode($data), LOCK_EX);
    }
    
    // 日志记录
    private function logAttack($type, $ip, $details = '') {
        $logEntry = sprintf(
            "[%s] %s - %s - %s - %s\n",
            date('Y-m-d H:i:s'),
            $type,
            $ip,
            $_SERVER['REQUEST_URI'] ?? '',
            $details
        );
        
        file_put_contents('security/attack_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    // 响应处理
    private function sendRateLimitResponse() {
        http_response_code(429);
        header('Retry-After: 60');
        die('请求过于频繁，请稍后再试。');
    }
    
    private function sendForbiddenResponse() {
        http_response_code(403);
        die('访问被拒绝。');
    }
    
    // 主防护函数
    public function protect() {
        $this->checkUserAgent();
        $this->checkHeaders();
        $this->checkRateLimit();
    }
}

// 全局防护函数
function initSecurityProtection() {
    $security = SecurityProtection::getInstance();
    $security->protect();
}

// 自动启动防护（在需要防护的页面包含此文件并调用）
// initSecurityProtection();
?>
