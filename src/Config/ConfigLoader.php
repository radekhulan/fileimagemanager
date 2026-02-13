<?php

declare(strict_types=1);

namespace RFM\Config;

final class ConfigLoader
{
    private string $configDir;

    public function __construct(string $configPath)
    {
        $this->configDir = dirname($configPath);
    }

    public function load(string $configPath): AppConfig
    {
        $config = $this->loadFile($configPath);

        // Merge ext arrays if ext_blacklist is set
        if (!empty($config['ext_blacklist'])) {
            $config['ext'] = array_merge(
                $config['ext_img'] ?? [],
                $config['ext_file'] ?? [],
                $config['ext_misc'] ?? [],
                $config['ext_video'] ?? [],
                $config['ext_music'] ?? [],
            );
        } else {
            $config['ext'] = $config['ext'] ?? array_merge(
                $config['ext_img'] ?? [],
                $config['ext_file'] ?? [],
                $config['ext_misc'] ?? [],
                $config['ext_video'] ?? [],
                $config['ext_music'] ?? [],
            );
        }

        return AppConfig::fromArray($config);
    }

    /**
     * Load and merge a per-folder config.php into existing config.
     */
    public function loadFolderConfig(AppConfig $baseConfig, string $folderPath): AppConfig
    {
        // Use JSON config files to prevent code execution from upload directories
        $configFile = rtrim($folderPath, '/\\') . DIRECTORY_SEPARATOR . '.rfm.config.json';

        if (!is_file($configFile)) {
            return $baseConfig;
        }

        $content = file_get_contents($configFile);
        if ($content === false) {
            return $baseConfig;
        }

        $folderConfig = json_decode($content, true);
        if (!is_array($folderConfig) || empty($folderConfig)) {
            return $baseConfig;
        }

        // Never allow overriding security-critical settings from folder configs
        unset(
            $folderConfig['ext_blacklist'],
            $folderConfig['use_access_keys'],
            $folderConfig['access_keys'],
            $folderConfig['current_path'],
            $folderConfig['thumbs_base_path'],
            $folderConfig['base_url'],
            $folderConfig['cors_allowed_origins'],
            $folderConfig['debug_error_message'],
        );

        // Merge folder config over base config
        $baseArray = $this->configToArray($baseConfig);
        $merged = array_merge($baseArray, $folderConfig);

        // Recalculate ext array
        $merged['ext'] = array_merge(
            $merged['ext_img'] ?? [],
            $merged['ext_file'] ?? [],
            $merged['ext_misc'] ?? [],
            $merged['ext_video'] ?? [],
            $merged['ext_music'] ?? [],
        );

        return AppConfig::fromArray($merged);
    }

    /**
     * Search up the directory tree for folder-specific config files.
     */
    public function loadHierarchicalConfig(AppConfig $baseConfig, string $currentFolder, string $basePath): AppConfig
    {
        $basePath = realpath($basePath);
        if ($basePath === false) {
            return $baseConfig;
        }

        $currentPath = realpath($currentFolder);
        if ($currentPath === false) {
            return $baseConfig;
        }

        // Build list of directories from base to current
        $directories = [];
        $path = $currentPath;

        while (str_starts_with($path, $basePath) && $path !== $basePath) {
            $directories[] = $path;
            $path = dirname($path);
        }

        // Apply configs from root toward current (more specific overrides general)
        $config = $baseConfig;
        foreach (array_reverse($directories) as $dir) {
            $config = $this->loadFolderConfig($config, $dir);
        }

        return $config;
    }

    private function loadFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $result = require $path;

        return is_array($result) ? $result : [];
    }

    private function configToArray(AppConfig $config): array
    {
        return [
            'base_url' => $config->baseUrl,
            'upload_dir' => $config->uploadDir,
            'thumbs_upload_dir' => $config->thumbsUploadDir,
            'current_path' => $config->currentPath,
            'thumbs_base_path' => $config->thumbsBasePath,
            'mime_extension_rename' => $config->mimeExtensionRename,
            'MaxSizeTotal' => $config->maxSizeTotal,
            'MaxSizeUpload' => $config->maxSizeUpload,
            'filePermission' => $config->filePermission,
            'folderPermission' => $config->folderPermission,
            'multiple_selection' => $config->multipleSelection,
            'multiple_selection_action_button' => $config->multipleSelectionActionButton,
            'default_language' => $config->defaultLanguage,
            'icon_theme' => $config->iconTheme,
            'show_total_size' => $config->showTotalSize,
            'show_folder_size' => $config->showFolderSize,
            'show_sorting_bar' => $config->showSortingBar,
            'show_filter_buttons' => $config->showFilterButtons,
            'show_language_selection' => $config->showLanguageSelection,
            'default_view' => $config->defaultView,
            'ellipsis_title_after_first_row' => $config->ellipsisTitleAfterFirstRow,
            'transliteration' => $config->transliteration,
            'convert_spaces' => $config->convertSpaces,
            'replace_with' => $config->replaceWith,
            'lower_case' => $config->lowerCase,
            'empty_filename' => $config->emptyFilename,
            'files_without_extension' => $config->filesWithoutExtension,
            'add_time_to_img' => $config->addTimeToImg,
            'image_max_width' => $config->imageMaxWidth,
            'image_max_height' => $config->imageMaxHeight,
            'image_max_mode' => $config->imageMaxMode,
            'image_resizing' => $config->imageResizing,
            'image_resizing_width' => $config->imageResizingWidth,
            'image_resizing_height' => $config->imageResizingHeight,
            'image_resizing_mode' => $config->imageResizingMode,
            'image_resizing_override' => $config->imageResizingOverride,
            'image_watermark' => $config->imageWatermark,
            'image_watermark_position' => $config->imageWatermarkPosition,
            'image_watermark_padding' => $config->imageWatermarkPadding,
            'delete_files' => $config->deleteFiles,
            'create_folders' => $config->createFolders,
            'delete_folders' => $config->deleteFolders,
            'upload_files' => $config->uploadFiles,
            'rename_files' => $config->renameFiles,
            'rename_folders' => $config->renameFolders,
            'duplicate_files' => $config->duplicateFiles,
            'extract_files' => $config->extractFiles,
            'copy_cut_files' => $config->copyCutFiles,
            'copy_cut_dirs' => $config->copyCutDirs,
            'chmod_files' => $config->chmodFiles,
            'chmod_dirs' => $config->chmodDirs,
            'preview_text_files' => $config->previewTextFiles,
            'edit_text_files' => $config->editTextFiles,
            'create_text_files' => $config->createTextFiles,
            'download_files' => $config->downloadFiles,
            'url_upload' => $config->urlUpload,
            'ext_img' => $config->extImg,
            'ext_file' => $config->extFile,
            'ext_video' => $config->extVideo,
            'ext_music' => $config->extMusic,
            'ext_misc' => $config->extMisc,
            'ext' => $config->ext,
            'ext_blacklist' => $config->extBlacklist,
            'editable_text_file_exts' => $config->editableTextFileExts,
            'previewable_text_file_exts' => $config->previewableTextFileExts,
            'jplayer_exts' => $config->jplayerExts,
            'cad_exts' => $config->cadExts,
            'googledoc_enabled' => $config->googledocEnabled,
            'googledoc_file_exts' => $config->googledocFileExts,
            'copy_cut_max_size' => $config->copyCutMaxSize,
            'copy_cut_max_count' => $config->copyCutMaxCount,
            'hidden_folders' => $config->hiddenFolders,
            'hidden_files' => $config->hiddenFiles,
            'file_number_limit_js' => $config->fileNumberLimitJs,
            'remember_text_filter' => $config->rememberTextFilter,
            'image_editor_active' => $config->imageEditorActive,
            'image_editor_position' => $config->imageEditorPosition,
            'dark_mode' => $config->darkMode,
            'remove_header' => $config->removeHeader,
            'use_access_keys' => $config->useAccessKeys,
            'access_keys' => $config->accessKeys,
            'fixed_image_creation' => $config->fixedImageCreation,
            'fixed_path_from_filemanager' => $config->fixedPathFromFilemanager,
            'fixed_image_creation_name_to_prepend' => $config->fixedImageCreationNameToPrepend,
            'fixed_image_creation_to_append' => $config->fixedImageCreationToAppend,
            'fixed_image_creation_width' => $config->fixedImageCreationWidth,
            'fixed_image_creation_height' => $config->fixedImageCreationHeight,
            'fixed_image_creation_option' => $config->fixedImageCreationOption,
            'relative_image_creation' => $config->relativeImageCreation,
            'relative_path_from_current_pos' => $config->relativePathFromCurrentPos,
            'relative_image_creation_name_to_prepend' => $config->relativeImageCreationNameToPrepend,
            'relative_image_creation_name_to_append' => $config->relativeImageCreationNameToAppend,
            'relative_image_creation_width' => $config->relativeImageCreationWidth,
            'relative_image_creation_height' => $config->relativeImageCreationHeight,
            'relative_image_creation_option' => $config->relativeImageCreationOption,
            'cors_allowed_origins' => $config->corsAllowedOrigins,
        ];
    }
}
