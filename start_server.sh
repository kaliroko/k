#!/bin/bash

# 网站启动脚本
# 作者: kalirok
# 描述: 启动PHP内置服务器并提供网站管理功能

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 配置参数
PORT=8080
HOST="0.0.0.0"
DOCUMENT_ROOT="."
SECURITY_CLEANUP_INTERVAL=3600 # 1小时清理一次安全数据

# 显示欢迎信息
show_welcome() {
    echo -e "${GREEN}"
    echo "  ███████╗██╗  ██╗██████╗ ███████╗██████╗ ██╗██╗"
    echo "  ██╔════╝╚██╗██╔╝██╔══██╗██╔════╝██╔══██╗██║██║"
    echo "  █████╗   ╚███╔╝ ██████╔╝█████╗  ██║  ██║██║██║"
    echo "  ██╔══╝   ██╔██╗ ██╔═══╝ ██╔══╝  ██║  ██║╚═╝╚═╝"
    echo "  ███████╗██╔╝ ██╗██║     ███████╗██████╔╝██╗██╗"
    echo "  ╚══════╝╚═╝  ╚═╝╚═╝     ╚══════╝╚═════╝ ╚═╝╚═╝"
    echo -e "${NC}"
    echo -e "${BLUE}三中万能墙 - 网站启动脚本${NC}"
    echo -e "${YELLOW}================================${NC}"
    echo ""
}

# 检查依赖
check_dependencies() {
    echo -e "${BLUE}[INFO] 检查系统依赖...${NC}"
    
    # 检查PHP
    if ! command -v php &> /dev/null; then
        echo -e "${RED}[ERROR] PHP未安装，请先安装PHP${NC}"
        exit 1
    fi
    
    # 检查PHP版本
    PHP_VERSION=$(php -v | grep -oP 'PHP \K[0-9]+\.[0-9]+' | head -1)
    echo -e "${GREEN}[OK] PHP版本: ${PHP_VERSION}${NC}"
    
    # 检查必要扩展
    REQUIRED_EXTENSIONS=("mbstring" "gd" "json")
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if php -m | grep -q "$ext"; then
            echo -e "${GREEN}[OK] PHP扩展: $ext${NC}"
        else
            echo -e "${YELLOW}[WARNING] PHP扩展 $ext 未安装，某些功能可能受限${NC}"
        fi
    done
    
    echo ""
}

# 检查目录结构
check_directories() {
    echo -e "${BLUE}[INFO] 检查目录结构...${NC}"
    
    DIRECTORIES=("posts" "uploads" "uploads/thumbs" "security" "css" "js")
    
    for dir in "${DIRECTORIES[@]}"; do
        if [ -d "$dir" ]; then
            echo -e "${GREEN}[OK] 目录存在: $dir${NC}"
        else
            echo -e "${YELLOW}[WARNING] 目录不存在: $dir，正在创建...${NC}"
            mkdir -p "$dir"
            chmod 755 "$dir"
        fi
    done
    
    echo ""
}

# 检查文件权限
check_permissions() {
    echo -e "${BLUE}[INFO] 检查文件权限...${NC}"
    
    # 检查关键文件是否存在
    IMPORTANT_FILES=("index.php" "kali.php" "post.php" "upload.php" "security.php")
    
    for file in "${IMPORTANT_FILES[@]}"; do
        if [ -f "$file" ]; then
            echo -e "${GREEN}[OK] 文件存在: $file${NC}"
        else
            echo -e "${RED}[ERROR] 关键文件缺失: $file${NC}"
            exit 1
        fi
    done
    
    # 设置上传目录权限
    chmod 755 uploads
    chmod 755 uploads/thumbs
    chmod 644 uploads/* 2>/dev/null || true
    
    echo ""
}
# 启动PHP内置服务器
start_server() {
    echo -e "${BLUE}[INFO] 启动PHP内置服务器...${NC}"
    
    # 检查端口是否被占用
    if lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null ; then
        echo -e "${YELLOW}[WARNING] 端口 $PORT 已被占用，尝试使用端口 8081${NC}"
        PORT=8081
    fi
    
    echo -e "${GREEN}[INFO] 服务器地址: http://$HOST:$PORT${NC}"
    echo -e "${GREEN}[INFO] 文档根目录: $DOCUMENT_ROOT${NC}"
    echo -e "${YELLOW}[TIP] 按 Ctrl+C 停止服务器${NC}"
    echo ""
    
    # 启动服务器
    php -S $HOST:$PORT -t $DOCUMENT_ROOT &
    SERVER_PID=$!
    echo $SERVER_PID > server.pid
    
    echo -e "${GREEN}[OK] 服务器已启动 (PID: $SERVER_PID)${NC}"
    echo ""
}

# 启动cpolar内网穿透
start_cpolar() {
    echo -e "${BLUE}[INFO] 启动cpolar内网穿透...${NC}"
    
    # 检查cpolar是否已安装
    if ! command -v cpolar &> /dev/null; then
        echo -e "${YELLOW}[WARNING] cpolar未安装，跳过内网穿透启动${NC}"
        return 0
    fi
    
    # 启动cpolar http 8080
    cpolar http 8080 &
    CPOLAR_PID=$!
    echo $CPOLAR_PID > cpolar.pid
    
    echo -e "${GREEN}[OK] cpolar内网穿透已启动 (PID: $CPOLAR_PID)${NC}"
    echo -e "${YELLOW}[TIP] 外网访问地址可通过 cpolar status 查看${NC}"
    echo ""
}

# 显示状态信息
show_status() {
    echo -e "${BLUE}[INFO] 服务器状态信息${NC}"
    echo -e "${YELLOW}================================${NC}"
    
    if [ -f server.pid ] && ps -p $(cat server.pid) > /dev/null 2>&1; then
        echo -e "${GREEN}✓ 网站服务器运行中 (PID: $(cat server.pid))${NC}"
    else
        echo -e "${RED}✗ 网站服务器未运行${NC}"
    fi
    
    
    
    if [ -f cpolar.pid ] && ps -p $(cat cpolar.pid) > /dev/null 2>&1; then
        echo -e "${GREEN}✓ cpolar内网穿透运行中 (PID: $(cat cpolar.pid))${NC}"
        echo -e "${YELLOW}🌐 外网访问: 运行 cpolar status 查看地址${NC}"
    else
        echo -e "${RED}✗ cpolar内网穿透未运行${NC}"
    fi
    
    # 显示安全统计
    if [ -f security/attack_log.txt ]; then
        ATTACK_COUNT=$(wc -l < security/attack_log.txt 2>/dev/null || echo 0)
        echo -e "${YELLOW}📊 安全事件记录: $ATTACK_COUNT 条${NC}"
    fi
    
    if [ -f security/ip_blacklist.txt ]; then
        BLACKLIST_COUNT=$(wc -l < security/ip_blacklist.txt 2>/dev/null || echo 0)
        echo -e "${YELLOW}🚫 黑名单IP数量: $BLACKLIST_COUNT 个${NC}"
    fi
    
    echo ""
}

# 停止服务
stop_services() {
    echo -e "${BLUE}[INFO] 停止所有服务...${NC}"
    
    if [ -f server.pid ] && ps -p $(cat server.pid) > /dev/null 2>&1; then
        kill $(cat server.pid)
        rm server.pid
        echo -e "${GREEN}[OK] 网站服务器已停止${NC}"
    fi
    
    
    if [ -f cpolar.pid ] && ps -p $(cat cpolar.pid) > /dev/null 2>&1; then
        kill $(cat cpolar.pid)
        rm cpolar.pid
        echo -e "${GREEN}[OK] cpolar内网穿透已停止${NC}"
    fi
    
 }

# 显示帮助信息
show_help() {
    echo -e "${BLUE}使用方法:${NC}"
    echo "  $0 start     - 启动网站服务"
    echo "  $0 stop      - 停止网站服务"
    echo "  $0 restart   - 重启网站服务"
    echo "  $0 status    - 显示服务状态"
    echo "  $0 cleanup   - 手动执行安全数据清理"
    echo "  $0 help      - 显示此帮助信息"
    echo ""
    echo -e "${YELLOW}示例:${NC}"
    echo "  $0 start     # 启动服务"
    echo "  $0 status    # 查看状态"
    echo "  $0 stop      # 停止服务"
    echo ""
}

# 主函数
main() {
    case "$1" in
        "start")
            show_welcome
            check_dependencies
            check_directories
            check_permissions
            start_security_cleanup
            start_server
            start_cpolar
            show_status
            
            # 等待服务器进程
            wait $SERVER_PID
            ;;
        "stop")
            stop_services
            ;;
        "restart")
            stop_services
            sleep 2
            $0 start
            ;;
        "status")
            show_status
            ;;
        "cleanup")
            echo -e "${BLUE}[INFO] 执行安全数据清理...${NC}"
            php security_cleanup.php
            echo -e "${GREEN}[OK] 清理完成${NC}"
            ;;
        "help"|""|"*")
            show_welcome
            show_help
            ;;
    esac
}

# 信号处理
trap 'echo -e "\n${YELLOW}[INFO] 收到中断信号，正在停止服务...${NC}"; stop_services; exit 0' INT TERM

# 执行主函数
main "$@"