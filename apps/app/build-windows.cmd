@echo off
setlocal
for %%I in ("%~dp0..\..") do set "REPO_ROOT=%%~fI"

call "C:\BuildTools\Common7\Tools\VsDevCmd.bat" -arch=x64
if errorlevel 1 exit /b %errorlevel%
set "PATH=C:\Users\Administrator\.rustup\toolchains\stable-x86_64-pc-windows-msvc\bin;%PATH%"
set "CARGO_TARGET_DIR=%REPO_ROOT%\target-original-ui"
set "CARGO_HOME=%REPO_ROOT%\..\..\cache\cargo-home"
set "CARGO_TARGET_X86_64_PC_WINDOWS_MSVC_LINKER=link.exe"

if exist "C:\Users\Administrator\AppData\Roaming\.minecraft\runtime\java-runtime-delta\bin\java.exe" set "JAVA_HOME=C:\Users\Administrator\AppData\Roaming\.minecraft\runtime\java-runtime-delta"
set "PATH=%JAVA_HOME%\bin;%PATH%"
set "GRADLE_USER_HOME=%REPO_ROOT%\.gradle-offline"
set "GRADLE_OPTS=-Dorg.gradle.offline=true"
set "GRADLE_EXECUTABLE=%GRADLE_USER_HOME%\wrapper\dists\gradle-9.1.0-bin\a7zz1zpvyl3jaouarz82m4yky\gradle-9.1.0\bin\gradle.bat"
if not exist "%GRADLE_EXECUTABLE%" (
	set "GRADLE_EXECUTABLE="
	set "GRADLE_OPTS="
)

set "THESEUS_PREBUILT_JAVA_DIR=%CARGO_TARGET_DIR%\prebuilt-java\libs"
if not exist "%THESEUS_PREBUILT_JAVA_DIR%" mkdir "%THESEUS_PREBUILT_JAVA_DIR%"
pushd "%REPO_ROOT%\packages\app-lib\java"
if defined GRADLE_EXECUTABLE (
    call "%GRADLE_EXECUTABLE%" "-Dorg.gradle.project.buildDir=%CARGO_TARGET_DIR%\prebuilt-java" build --offline --no-daemon --console=plain
) else (
    call gradlew.bat "-Dorg.gradle.project.buildDir=%CARGO_TARGET_DIR%\prebuilt-java" build --no-daemon --console=plain
)
if errorlevel 1 (
    popd
    exit /b 1
)
popd

set "DEFAULT_SIGNING_KEY_PATH=%REPO_ROOT%\..\..\outputs\BlockEngine-Update-Keys\block-engine.key"
if not defined BLOCK_ENGINE_SIGNING_KEY_PATH if exist "%DEFAULT_SIGNING_KEY_PATH%" set "BLOCK_ENGINE_SIGNING_KEY_PATH=%DEFAULT_SIGNING_KEY_PATH%"
if defined BLOCK_ENGINE_SIGNING_KEY_PATH (
	if not exist "%BLOCK_ENGINE_SIGNING_KEY_PATH%" (
		echo [ERROR] Update signing key not found: %BLOCK_ENGINE_SIGNING_KEY_PATH%
		exit /b 2
	)
	set /p TAURI_SIGNING_PRIVATE_KEY=<"%BLOCK_ENGINE_SIGNING_KEY_PATH%"
	set "TAURI_SIGNING_PRIVATE_KEY_PASSWORD="
) else (
	set "TAURI_SIGNING_PRIVATE_KEY="
	set "TAURI_SIGNING_PRIVATE_KEY_PASSWORD="
	echo [INFO] Updater artifacts are disabled; building without an update signing key.
)

set "NODE_EXE=node"
if exist "C:\Users\Administrator\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe" set "NODE_EXE=C:\Users\Administrator\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe"

pushd "%REPO_ROOT%"
cargo build --release --package axolotl-installer-ui
if errorlevel 1 (
	popd
	exit /b 1
)
popd

pushd "%~dp0\..\app-frontend"
"%NODE_EXE%" "node_modules\vue-tsc\bin\vue-tsc.js" --noEmit
if errorlevel 1 (
	popd
	exit /b 1
)
"%NODE_EXE%" "node_modules\vite\bin\vite.js" build
if errorlevel 1 (
	popd
	exit /b 1
)
popd

pushd "%~dp0"
"%NODE_EXE%" "node_modules\@tauri-apps\cli\tauri.js" build --config tauri.build.config.json --ci
set "BUILD_EXIT=%errorlevel%"
popd
exit /b %BUILD_EXIT%
