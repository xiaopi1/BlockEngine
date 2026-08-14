@echo off
setlocal
cd /d "%~dp0"

call "C:\BuildTools\Common7\Tools\VsDevCmd.bat" -arch=x64
if errorlevel 1 exit /b %ERRORLEVEL%
set "PATH=C:\Users\Administrator\.rustup\toolchains\stable-x86_64-pc-windows-msvc\bin;%PATH%"

if not defined BLOCK_ENGINE_SIGNING_KEY_PATH set "BLOCK_ENGINE_SIGNING_KEY_PATH=E:\codex\private\BlockEngine-Update-Keys\block-engine.key"
if not exist "%BLOCK_ENGINE_SIGNING_KEY_PATH%" (
  echo [ERROR] Update signing key not found: %BLOCK_ENGINE_SIGNING_KEY_PATH%
  exit /b 1
)

set /p TAURI_SIGNING_PRIVATE_KEY=<"%BLOCK_ENGINE_SIGNING_KEY_PATH%"
set "TAURI_SIGNING_PRIVATE_KEY_PASSWORD="
set "CARGO_TARGET_DIR=E:\codex\outputs\target-update-test-shell"
set "CARGO_HOME=E:\codex\cache\cargo-home"
set "CARGO_TARGET_X86_64_PC_WINDOWS_MSVC_LINKER=link.exe"
"C:\Users\Administrator\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe" "%~dp0..\..\apps\app\node_modules\@tauri-apps\cli\tauri.js" build --config tauri-release.conf.json --ci
exit /b %ERRORLEVEL%
