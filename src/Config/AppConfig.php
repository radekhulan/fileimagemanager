<?php

declare(strict_types=1);

namespace RFM\Config;

final readonly class AppConfig
{
    /**
     * @param string[] $extImg
     * @param string[] $extFile
     * @param string[] $extVideo
     * @param string[] $extMusic
     * @param string[] $extMisc
     * @param string[] $ext
     * @param string[] $extBlacklist
     * @param string[] $hiddenFolders
     * @param string[] $hiddenFiles
     * @param string[] $editableTextFileExts
     * @param string[] $previewableTextFileExts
     * @param string[] $jplayerExts
     * @param string[] $cadExts
     * @param string[] $googledocFileExts
     * @param string[] $accessKeys
     * @param string[] $fixedPathFromFilemanager
     * @param string[] $fixedImageCreationNameToPrepend
     * @param string[] $fixedImageCreationToAppend
     * @param int[] $fixedImageCreationWidth
     * @param int[] $fixedImageCreationHeight
     * @param string[] $fixedImageCreationOption
     * @param string[] $relativePathFromCurrentPos
     * @param string[] $relativeImageCreationNameToPrepend
     * @param string[] $relativeImageCreationNameToAppend
     * @param int[] $relativeImageCreationWidth
     * @param int[] $relativeImageCreationHeight
     * @param string[] $relativeImageCreationOption
     */
    public function __construct(
        // Path configuration
        public string $baseUrl,
        public string $uploadDir,
        public string $thumbsUploadDir,
        public string $currentPath,
        public string $thumbsBasePath,

        // Upload settings
        public bool $autoUpload,
        public bool $mimeExtensionRename,
        public int|false $maxSizeTotal,
        public int $maxSizeUpload,
        public int $filePermission,
        public int $folderPermission,

        // Selection
        public bool $multipleSelection,
        public bool $multipleSelectionActionButton,

        // Language & UI
        public string $defaultLanguage,
        public string $iconTheme,
        public bool $showTotalSize,
        public bool $showFolderSize,
        public bool $showSortingBar,
        public bool $showFilterButtons,
        public bool $showLanguageSelection,
        public int $defaultView,
        public bool $ellipsisTitleAfterFirstRow,

        // Filename handling
        public bool $transliteration,
        public bool $convertSpaces,
        public string $replaceWith,
        public bool $lowerCase,
        public bool $emptyFilename,
        public bool $filesWithoutExtension,
        public bool $addTimeToImg,

        // Image limits
        public int $imageMaxWidth,
        public int $imageMaxHeight,
        public string $imageMaxMode,
        public bool $imageResizing,
        public int $imageResizingWidth,
        public int $imageResizingHeight,
        public string $imageResizingMode,
        public bool $imageResizingOverride,

        // Watermark
        public string|false $imageWatermark,
        public string $imageWatermarkPosition,
        public int $imageWatermarkPadding,

        // Permissions
        public bool $deleteFiles,
        public bool $createFolders,
        public bool $deleteFolders,
        public bool $uploadFiles,
        public bool $renameFiles,
        public bool $renameFolders,
        public bool $duplicateFiles,
        public bool $extractFiles,
        public bool $copyCutFiles,
        public bool $copyCutDirs,
        public bool $chmodFiles,
        public bool $chmodDirs,
        public bool $previewTextFiles,
        public bool $editTextFiles,
        public bool $createTextFiles,
        public bool $downloadFiles,
        public bool $urlUpload,

        // Extensions
        public array $extImg,
        public array $extFile,
        public array $extVideo,
        public array $extMusic,
        public array $extMisc,
        public array $ext,
        public array $extBlacklist,

        // Special file types
        public array $editableTextFileExts,
        public array $previewableTextFileExts,
        public array $jplayerExts,
        public array $cadExts,
        public bool $googledocEnabled,
        public array $googledocFileExts,

        // Copy/cut limits
        public int|false $copyCutMaxSize,
        public int|false $copyCutMaxCount,

        // Hidden items
        public array $hiddenFolders,
        public array $hiddenFiles,

        // Performance
        public int $fileNumberLimitJs,
        public bool $rememberTextFilter,

        // Image editor
        public bool $imageEditorActive,
        public string $imageEditorPosition,

        // Dark mode
        public bool $darkMode,

        // TinyMCE header
        public bool $removeHeader,

        // Access keys
        public bool $useAccessKeys,
        public array $accessKeys,

        // Fixed thumbnail creation
        public bool $fixedImageCreation,
        public array $fixedPathFromFilemanager,
        public array $fixedImageCreationNameToPrepend,
        public array $fixedImageCreationToAppend,
        public array $fixedImageCreationWidth,
        public array $fixedImageCreationHeight,
        public array $fixedImageCreationOption,

        // Relative thumbnail creation
        public bool $relativeImageCreation,
        public array $relativePathFromCurrentPos,
        public array $relativeImageCreationNameToPrepend,
        public array $relativeImageCreationNameToAppend,
        public array $relativeImageCreationWidth,
        public array $relativeImageCreationHeight,
        public array $relativeImageCreationOption,

        // CORS
        /** @var string[] */
        public array $corsAllowedOrigins = [],

        // Debug
        public bool $debugErrorMessage = false,
    ) {}

    public static function fromArray(array $config): self
    {
        return new self(
            baseUrl: $config['base_url'] ?? self::detectBaseUrl(),
            uploadDir: $config['upload_dir'] ?? '/source/',
            thumbsUploadDir: $config['thumbs_upload_dir'] ?? '/thumbs/',
            currentPath: $config['current_path'] ?? '../source/',
            thumbsBasePath: $config['thumbs_base_path'] ?? '../thumbs/',

            autoUpload: (bool) ($config['auto_upload'] ?? true),
            mimeExtensionRename: (bool) ($config['mime_extension_rename'] ?? true),
            maxSizeTotal: isset($config['MaxSizeTotal']) && $config['MaxSizeTotal'] !== false
                ? (int) $config['MaxSizeTotal'] : false,
            maxSizeUpload: (int) ($config['MaxSizeUpload'] ?? 10),
            filePermission: (int) ($config['filePermission'] ?? 0644),
            folderPermission: (int) ($config['folderPermission'] ?? 0755),

            multipleSelection: (bool) ($config['multiple_selection'] ?? true),
            multipleSelectionActionButton: (bool) ($config['multiple_selection_action_button'] ?? true),

            defaultLanguage: (string) ($config['default_language'] ?? 'en_EN'),
            iconTheme: (string) ($config['icon_theme'] ?? 'ico'),
            showTotalSize: (bool) ($config['show_total_size'] ?? false),
            showFolderSize: (bool) ($config['show_folder_size'] ?? false),
            showSortingBar: (bool) ($config['show_sorting_bar'] ?? true),
            showFilterButtons: (bool) ($config['show_filter_buttons'] ?? true),
            showLanguageSelection: (bool) ($config['show_language_selection'] ?? true),
            defaultView: (int) ($config['default_view'] ?? 0),
            ellipsisTitleAfterFirstRow: (bool) ($config['ellipsis_title_after_first_row'] ?? true),

            transliteration: (bool) ($config['transliteration'] ?? true),
            convertSpaces: (bool) ($config['convert_spaces'] ?? true),
            replaceWith: (string) ($config['replace_with'] ?? '_'),
            lowerCase: (bool) ($config['lower_case'] ?? false),
            emptyFilename: (bool) ($config['empty_filename'] ?? false),
            filesWithoutExtension: (bool) ($config['files_without_extension'] ?? false),
            addTimeToImg: (bool) ($config['add_time_to_img'] ?? false),

            imageMaxWidth: (int) ($config['image_max_width'] ?? 0),
            imageMaxHeight: (int) ($config['image_max_height'] ?? 0),
            imageMaxMode: (string) ($config['image_max_mode'] ?? 'auto'),
            imageResizing: (bool) ($config['image_resizing'] ?? false),
            imageResizingWidth: (int) ($config['image_resizing_width'] ?? 0),
            imageResizingHeight: (int) ($config['image_resizing_height'] ?? 0),
            imageResizingMode: (string) ($config['image_resizing_mode'] ?? 'auto'),
            imageResizingOverride: (bool) ($config['image_resizing_override'] ?? false),

            imageWatermark: isset($config['image_watermark']) && $config['image_watermark'] !== false
                ? (string) $config['image_watermark'] : false,
            imageWatermarkPosition: (string) ($config['image_watermark_position'] ?? 'br'),
            imageWatermarkPadding: (int) ($config['image_watermark_padding'] ?? 10),

            deleteFiles: (bool) ($config['delete_files'] ?? true),
            createFolders: (bool) ($config['create_folders'] ?? true),
            deleteFolders: (bool) ($config['delete_folders'] ?? true),
            uploadFiles: (bool) ($config['upload_files'] ?? true),
            renameFiles: (bool) ($config['rename_files'] ?? true),
            renameFolders: (bool) ($config['rename_folders'] ?? true),
            duplicateFiles: (bool) ($config['duplicate_files'] ?? true),
            extractFiles: (bool) ($config['extract_files'] ?? false),
            copyCutFiles: (bool) ($config['copy_cut_files'] ?? true),
            copyCutDirs: (bool) ($config['copy_cut_dirs'] ?? true),
            chmodFiles: (bool) ($config['chmod_files'] ?? false),
            chmodDirs: (bool) ($config['chmod_dirs'] ?? false),
            previewTextFiles: (bool) ($config['preview_text_files'] ?? false),
            editTextFiles: (bool) ($config['edit_text_files'] ?? false),
            createTextFiles: (bool) ($config['create_text_files'] ?? false),
            downloadFiles: (bool) ($config['download_files'] ?? true),
            urlUpload: (bool) ($config['url_upload'] ?? false),

            extImg: $config['ext_img'] ?? ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'webp'],
            extFile: $config['ext_file'] ?? ['doc', 'docx', 'xls', 'xlsx', 'pdf'],
            extVideo: $config['ext_video'] ?? ['mov', 'mpeg', 'm4v', 'mp4', 'avi', 'mpg', 'wma', 'flv', 'webm'],
            extMusic: $config['ext_music'] ?? ['mp3', 'mpga', 'm4a', 'ac3', 'aiff', 'mid', 'ogg', 'wav'],
            extMisc: $config['ext_misc'] ?? ['zip', 'rar', 'gz', 'tar'],
            ext: $config['ext'] ?? [],
            extBlacklist: $config['ext_blacklist'] ?? [],

            editableTextFileExts: $config['editable_text_file_exts'] ?? ['txt', 'log'],
            previewableTextFileExts: $config['previewable_text_file_exts'] ?? ['txt', 'log', 'xml', 'css'],
            jplayerExts: $config['jplayer_exts'] ?? ['mp4', 'flv', 'webmv', 'webma', 'webm', 'm4a', 'm4v', 'ogv', 'oga', 'mp3', 'midi', 'mid', 'ogg', 'wav'],
            cadExts: $config['cad_exts'] ?? ['dwg', 'dxf', 'hpgl', 'plt', 'spl', 'step', 'stp', 'iges', 'igs', 'sat', 'cgm', 'svg'],
            googledocEnabled: (bool) ($config['googledoc_enabled'] ?? true),
            googledocFileExts: $config['googledoc_file_exts'] ?? ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf', 'odt', 'odp', 'ods'],

            copyCutMaxSize: isset($config['copy_cut_max_size']) && $config['copy_cut_max_size'] !== false
                ? (int) $config['copy_cut_max_size'] : false,
            copyCutMaxCount: isset($config['copy_cut_max_count']) && $config['copy_cut_max_count'] !== false
                ? (int) $config['copy_cut_max_count'] : false,

            hiddenFolders: $config['hidden_folders'] ?? [],
            hiddenFiles: $config['hidden_files'] ?? ['config.php'],

            fileNumberLimitJs: (int) ($config['file_number_limit_js'] ?? 500),
            rememberTextFilter: (bool) ($config['remember_text_filter'] ?? false),

            imageEditorActive: (bool) ($config['tui_active'] ?? $config['image_editor_active'] ?? true),
            imageEditorPosition: (string) ($config['tui_position'] ?? $config['image_editor_position'] ?? 'bottom'),

            darkMode: (bool) ($config['dark_mode'] ?? true),
            removeHeader: (bool) ($config['remove_header'] ?? true),

            useAccessKeys: (bool) ($config['use_access_keys'] ?? defined('USE_ACCESS_KEYS') && USE_ACCESS_KEYS),
            accessKeys: $config['access_keys'] ?? [],

            fixedImageCreation: (bool) ($config['fixed_image_creation'] ?? false),
            fixedPathFromFilemanager: $config['fixed_path_from_filemanager'] ?? [],
            fixedImageCreationNameToPrepend: $config['fixed_image_creation_name_to_prepend'] ?? [],
            fixedImageCreationToAppend: $config['fixed_image_creation_to_append'] ?? [],
            fixedImageCreationWidth: array_map('intval', $config['fixed_image_creation_width'] ?? []),
            fixedImageCreationHeight: array_map('intval', $config['fixed_image_creation_height'] ?? []),
            fixedImageCreationOption: $config['fixed_image_creation_option'] ?? [],

            relativeImageCreation: (bool) ($config['relative_image_creation'] ?? false),
            relativePathFromCurrentPos: $config['relative_path_from_current_pos'] ?? [],
            relativeImageCreationNameToPrepend: $config['relative_image_creation_name_to_prepend'] ?? [],
            relativeImageCreationNameToAppend: $config['relative_image_creation_name_to_append'] ?? [],
            relativeImageCreationWidth: array_map('intval', $config['relative_image_creation_width'] ?? []),
            relativeImageCreationHeight: array_map('intval', $config['relative_image_creation_height'] ?? []),
            relativeImageCreationOption: $config['relative_image_creation_option'] ?? [],

            corsAllowedOrigins: $config['cors_allowed_origins'] ?? [],

            debugErrorMessage: (bool) ($config['debug_error_message'] ?? false),
        );
    }

    /**
     * Returns configuration values safe to send to the frontend client.
     * @return array<string, mixed>
     */
    public function toClientConfig(): array
    {
        return [
            'autoUpload' => $this->autoUpload,
            'uploadFiles' => $this->uploadFiles,
            'createFolders' => $this->createFolders,
            'deleteFolders' => $this->deleteFolders,
            'deleteFiles' => $this->deleteFiles,
            'renameFiles' => $this->renameFiles,
            'renameFolders' => $this->renameFolders,
            'duplicateFiles' => $this->duplicateFiles,
            'copyCutFiles' => $this->copyCutFiles,
            'copyCutDirs' => $this->copyCutDirs,
            'chmodFiles' => $this->chmodFiles,
            'chmodDirs' => $this->chmodDirs,
            'extractFiles' => $this->extractFiles,
            'previewTextFiles' => $this->previewTextFiles,
            'editTextFiles' => $this->editTextFiles,
            'createTextFiles' => $this->createTextFiles,
            'downloadFiles' => $this->downloadFiles,
            'urlUpload' => $this->urlUpload,
            'multipleSelection' => $this->multipleSelection,
            'multipleSelectionActionButton' => $this->multipleSelectionActionButton,
            'showTotalSize' => $this->showTotalSize,
            'showFolderSize' => $this->showFolderSize,
            'showSortingBar' => $this->showSortingBar,
            'showFilterButtons' => $this->showFilterButtons,
            'showLanguageSelection' => $this->showLanguageSelection,
            'imageEditorActive' => $this->imageEditorActive,
            'darkMode' => $this->darkMode,
            'removeHeader' => $this->removeHeader,
            'maxSizeUpload' => $this->maxSizeUpload,
            'fileNumberLimitJs' => $this->fileNumberLimitJs,
            'extImg' => $this->extImg,
            'extVideo' => $this->extVideo,
            'extMusic' => $this->extMusic,
            'extFile' => $this->extFile,
            'extMisc' => $this->extMisc,
            'baseUrl' => $this->baseUrl,
            'uploadDir' => $this->uploadDir,
            'editableTextFileExts' => $this->editableTextFileExts,
            'previewableTextFileExts' => $this->previewableTextFileExts,
            'addTimeToImg' => $this->addTimeToImg,
            'copyCutMaxSize' => $this->copyCutMaxSize,
            'copyCutMaxCount' => $this->copyCutMaxCount,
            'googledocEnabled' => $this->googledocEnabled,
            'googledocFileExts' => $this->googledocFileExts,
            'defaultView' => $this->defaultView,
        ];
    }

    /**
     * Get extension config arrays for FileCategory resolution.
     * @return array{ext_img: string[], ext_video: string[], ext_music: string[], ext_file: string[], ext_misc: string[]}
     */
    public function getExtConfig(): array
    {
        return [
            'ext_img' => $this->extImg,
            'ext_video' => $this->extVideo,
            'ext_music' => $this->extMusic,
            'ext_file' => $this->extFile,
            'ext_misc' => $this->extMisc,
        ];
    }

    private static function detectBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$scheme}://{$host}";
    }
}
