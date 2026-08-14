#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

use serde::Serialize;
use std::sync::{
    Arc,
    atomic::{AtomicU64, Ordering},
};
use tauri::{Emitter, Manager};
use tauri_plugin_updater::UpdaterExt;

#[derive(Clone, Serialize)]
#[serde(rename_all = "camelCase")]
struct UpdateProgress {
    stage: &'static str,
    message: String,
    version: Option<String>,
    downloaded: u64,
    total: Option<u64>,
}

fn emit_progress(app: &tauri::AppHandle, progress: UpdateProgress) {
    let _ = app.emit("blockengine://update-progress", progress);
}

#[tauri::command]
async fn run_update_test(app: tauri::AppHandle) -> Result<String, String> {
    emit_progress(
        &app,
        UpdateProgress {
            stage: "checking",
            message: "正在连接方块引擎官方更新服务…".to_string(),
            version: None,
            downloaded: 0,
            total: None,
        },
    );

    let updater = app.updater().map_err(|error| error.to_string())?;
    let Some(update) = updater.check().await.map_err(|error| error.to_string())? else {
        emit_progress(
            &app,
            UpdateProgress {
                stage: "current",
                message: "没有检测到比 1.7.1 更新的正式版本。".to_string(),
                version: None,
                downloaded: 0,
                total: None,
            },
        );
        return Ok("current".to_string());
    };

    let version = update.version.clone();
    emit_progress(
        &app,
        UpdateProgress {
            stage: "downloading",
            message: format!("检测到方块引擎 {version}，正在安全下载…"),
            version: Some(version.clone()),
            downloaded: 0,
            total: None,
        },
    );

    let downloaded = Arc::new(AtomicU64::new(0));
    let progress_app = app.clone();
    let progress_version = version.clone();
    let progress_downloaded = Arc::clone(&downloaded);
    let finished_app = app.clone();
    let finished_version = version.clone();

    update
        .download_and_install(
            move |chunk_size, total| {
                let downloaded = progress_downloaded
                    .fetch_add(chunk_size as u64, Ordering::Relaxed)
                    + chunk_size as u64;
                emit_progress(
                    &progress_app,
                    UpdateProgress {
                        stage: "downloading",
                        message: format!("正在下载方块引擎 {progress_version}…"),
                        version: Some(progress_version.clone()),
                        downloaded,
                        total,
                    },
                );
            },
            move || {
                emit_progress(
                    &finished_app,
                    UpdateProgress {
                        stage: "installing",
                        message: format!("{finished_version} 已下载并通过签名验证，正在安装…"),
                        version: Some(finished_version.clone()),
                        downloaded: 0,
                        total: None,
                    },
                );
            },
        )
        .await
        .map_err(|error| error.to_string())?;

    emit_progress(
        &app,
        UpdateProgress {
            stage: "installed",
            message: format!("方块引擎 {version} 安装程序已启动。"),
            version: Some(version),
            downloaded: 0,
            total: None,
        },
    );
    app.exit(0);
    Ok("installed".to_string())
}

fn main() {
    tauri::Builder::default()
        .plugin(tauri_plugin_updater::Builder::new().build())
        .invoke_handler(tauri::generate_handler![run_update_test])
        .setup(|app| {
            if let Some(window) = app.get_webview_window("main") {
                let _ = window.set_focus();
            }
            Ok(())
        })
        .run(tauri::generate_context!())
        .expect("failed to run BlockEngine update test shell");
}

