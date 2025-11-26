@echo off
echo 设置Windows终端编码为UTF-8...
chcp 65001 >nul

echo 设置WSL环境变量...
wsl -d Ubuntu bash -c "echo 'export LANG=C.UTF-8' > ~/.profile"
wsl -d Ubuntu bash -c "echo 'export LC_ALL=C.UTF-8' >> ~/.profile"
wsl -d Ubuntu bash -c "echo 'export WSL_UTF8=1' >> ~/.profile"

echo 重启WSL以应用更改...
wsl --shutdown

echo 编码设置完成！
echo 请重新启动VSCode并打开新的终端来测试中文显示。
pause
