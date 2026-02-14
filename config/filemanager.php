<?php

/**
 * File Image Manager v1.0.0 Configuration
 *
 * Per-folder overrides: create a .rfm.config.json file in any subfolder
 * to override settings for that folder and its children.
 * Security-critical settings (ext_blacklist, access_keys, paths) cannot be overridden.
 */

// Example: session-based access control
// Uncomment the following block to restrict access to users
// with $_SESSION['ImageEditorAllowed'] set to true.
//
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }
// if (empty($_SESSION['ImageEditorAllowed'])) {
//     http_response_code(403);
//     exit('Access denied');
// }

$config = [

    /*
    |--------------------------------------------------------------------------
    | Path configuration
    |--------------------------------------------------------------------------
    |
    | base_url            - Root URL of the site (auto-detected from HTTP_HOST).
    | upload_dir          - Public URL path to the upload directory (relative to base_url).
    | thumbs_upload_dir   - Public URL path to the thumbnails directory.
    | current_path        - Absolute filesystem path to the upload directory.
    | thumbs_base_path    - Absolute filesystem path to the thumbnails directory.
    |
    */
    'base_url' => ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . '://' . preg_replace('/[^\w.\-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'),
    'upload_dir' => '/media/source/',
    'thumbs_upload_dir' => '/media/thumbs/',
    'current_path' => rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR,
    'thumbs_base_path' => rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR,

    /*
    |--------------------------------------------------------------------------
    | Upload settings
    |--------------------------------------------------------------------------
    |
    | auto_upload            - Automatically start uploading files when they are selected
    |                          or dropped into the file manager (true = instant upload,
    |                          false = files are queued and user clicks "Upload").
    | mime_extension_rename  - Rename uploaded files to match their actual MIME type extension.
    | MaxSizeTotal           - Max total upload size in MB per request (false = unlimited).
    | MaxSizeUpload          - Max single file size in MB.
    | filePermission         - Unix permission for newly created files (octal).
    | folderPermission       - Unix permission for newly created folders (octal).
    |
    */
    'auto_upload' => true,
    'mime_extension_rename' => true,
    'MaxSizeTotal' => false,
    'MaxSizeUpload' => 10,
    'filePermission' => 0644,
    'folderPermission' => 0755,

    /*
    |--------------------------------------------------------------------------
    | Selection
    |--------------------------------------------------------------------------
    |
    | multiple_selection               - Allow selecting multiple files at once.
    | multiple_selection_action_button - Show bulk action buttons (delete, copy, etc.)
    |                                    when multiple files are selected.
    |
    */
    'multiple_selection' => true,
    'multiple_selection_action_button' => true,

    /*
    |--------------------------------------------------------------------------
    | Access keys
    |--------------------------------------------------------------------------
    |
    | use_access_keys - Require an access key (?akey=...) in the URL to open
    |                   the file manager. Useful when embedding in a CMS.
    | access_keys     - Array of allowed access key strings.
    |                   Example: ['my-secret-key-123', 'another-key']
    |
    */
    'use_access_keys' => false,
    'access_keys' => [],

    /*
    |--------------------------------------------------------------------------
    | Language & UI
    |--------------------------------------------------------------------------
    |
    | default_language              - Language code for translations (e.g. 'en_EN', 'cs_CS').
    |                                 Must match a filename in the lang/ directory.
    | icon_theme                    - Icon set to use ('ico' = built-in icons).
    | show_total_size               - Display total size of all files in the status bar.
    | show_folder_size              - Calculate and display individual folder sizes
    |                                 (may slow down listing on large directories).
    | show_sorting_bar              - Show the column sort bar in list/columns view.
    | show_filter_buttons           - Show file type filter buttons (images, video, etc.).
    | show_language_selection       - Show language picker in the toolbar.
    | default_view                  - Default view mode: 0 = grid, 1 = list, 2 = columns.
    | ellipsis_title_after_first_row - Truncate long filenames with "..." in grid view.
    |
    */
    'default_language' => 'en_EN',
    'icon_theme' => 'ico',
    'show_total_size' => false,
    'show_folder_size' => false,
    'show_sorting_bar' => true,
    'show_filter_buttons' => true,
    'show_language_selection' => true,
    'default_view' => 0,
    'ellipsis_title_after_first_row' => true,

    /*
    |--------------------------------------------------------------------------
    | Filename handling
    |--------------------------------------------------------------------------
    |
    | transliteration        - Convert accented characters to ASCII (e.g. "ě" -> "e").
    | convert_spaces         - Replace spaces in filenames with the 'replace_with' character.
    | replace_with           - Character used to replace spaces (default: underscore).
    | lower_case             - Force all filenames to lower case on upload.
    | empty_filename         - Allow empty filenames (extension only).
    | files_without_extension - Allow uploading files that have no extension.
    | add_time_to_img        - Append a timestamp to image filenames to prevent
    |                          browser caching issues after edits.
    |
    */
    'transliteration' => true,
    'convert_spaces' => true,
    'replace_with' => '_',
    'lower_case' => false,
    'empty_filename' => false,
    'files_without_extension' => false,
    'add_time_to_img' => false,

    /*
    |--------------------------------------------------------------------------
    | Image limits & resizing
    |--------------------------------------------------------------------------
    |
    | image_max_width          - Reject uploaded images wider than this (0 = no limit).
    | image_max_height         - Reject uploaded images taller than this (0 = no limit).
    | image_max_mode           - Resize mode when image exceeds max dimensions:
    |                            'auto', 'portrait', 'landscape', 'crop', 'exact'.
    | image_resizing           - Automatically resize images on upload.
    | image_resizing_width     - Target width for auto-resizing (0 = auto from ratio).
    | image_resizing_height    - Target height for auto-resizing (0 = auto from ratio).
    | image_resizing_mode      - Resize mode: 'auto', 'portrait', 'landscape', 'crop', 'exact'.
    | image_resizing_override  - Overwrite the original file with the resized version
    |                            (true) or save as a new file (false).
    |
    */
    'image_max_width' => 0,
    'image_max_height' => 0,
    'image_max_mode' => 'auto',
    'image_resizing' => false,
    'image_resizing_width' => 0,
    'image_resizing_height' => 0,
    'image_resizing_mode' => 'auto',
    'image_resizing_override' => false,

    /*
    |--------------------------------------------------------------------------
    | Watermark
    |--------------------------------------------------------------------------
    |
    | image_watermark          - Apply a watermark image to uploaded images.
    |                            Set to the absolute path of the watermark PNG file,
    |                            or false to disable.
    | image_watermark_position - Position: 'tl' (top-left), 'tr' (top-right),
    |                            'bl' (bottom-left), 'br' (bottom-right),
    |                            'center'.
    | image_watermark_padding  - Padding in pixels from the edge.
    |
    */
    'image_watermark' => false,
    'image_watermark_position' => 'br',
    'image_watermark_padding' => 10,

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | Controls which operations are available to the user.
    | Set to false to disable the corresponding action.
    |
    | delete_files       - Delete files.
    | create_folders     - Create new folders.
    | delete_folders     - Delete folders (including contents).
    | upload_files       - Upload files from the local computer.
    | rename_files       - Rename files.
    | rename_folders     - Rename folders.
    | duplicate_files    - Create a copy of a file in the same directory.
    | extract_files      - Extract ZIP/RAR/GZ/TAR archives.
    | copy_cut_files     - Copy or cut files to paste elsewhere.
    | copy_cut_dirs      - Copy or cut entire folders.
    | chmod_files        - Change Unix permissions on files.
    | chmod_dirs         - Change Unix permissions on folders.
    | preview_text_files - Preview text files inline.
    | edit_text_files    - Edit text files in a built-in editor.
    | create_text_files  - Create new empty text files.
    | download_files     - Download files to the local computer.
    | url_upload         - Upload files by providing an external URL.
    |
    */
    'delete_files' => true,
    'create_folders' => true,
    'delete_folders' => true,
    'upload_files' => true,
    'rename_files' => true,
    'rename_folders' => true,
    'duplicate_files' => true,
    'extract_files' => false,
    'copy_cut_files' => true,
    'copy_cut_dirs' => true,
    'chmod_files' => false,
    'chmod_dirs' => false,
    'preview_text_files' => true,
    'edit_text_files' => true,
    'create_text_files' => false,
    'download_files' => true,
    'url_upload' => true,

    /*
    |--------------------------------------------------------------------------
    | Text file extensions
    |--------------------------------------------------------------------------
    |
    | previewable_text_file_exts - Extensions that can be previewed inline.
    | editable_text_file_exts    - Extensions that can be edited in the built-in editor.
    |                              Only effective if the matching permission is enabled above.
    |
    */
    'previewable_text_file_exts' => ['txt', 'log', 'xml', 'css'],
    'editable_text_file_exts' => ['txt', 'log'],

    /*
    |--------------------------------------------------------------------------
    | Media extensions
    |--------------------------------------------------------------------------
    |
    | jplayer_exts - Extensions playable in the built-in HTML5 audio/video player.
    | cad_exts     - CAD/vector file extensions (used for file type detection only).
    |
    */
    'jplayer_exts' => ['mp4', 'flv', 'webmv', 'webma', 'webm', 'm4a', 'm4v', 'ogv', 'oga', 'mp3', 'midi', 'mid', 'ogg', 'wav'],
    'cad_exts' => ['dwg', 'dxf', 'hpgl', 'plt', 'spl', 'step', 'stp', 'iges', 'igs', 'sat', 'cgm', 'svg'],

    /*
    |--------------------------------------------------------------------------
    | Google Docs preview
    |--------------------------------------------------------------------------
    |
    | googledoc_enabled   - Use Google Docs Viewer for previewing office documents
    |                       and PDFs. Requires that the file is publicly accessible.
    |                       WARNING: Enabling this sends full file URLs to Google
    |                       servers, which may leak information about your files.
    | googledoc_file_exts - Extensions to open with Google Docs Viewer.
    |
    */
    'googledoc_enabled' => false,
    'googledoc_file_exts' => ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'odp', 'ods'],

    /*
    |--------------------------------------------------------------------------
    | Copy/cut limits
    |--------------------------------------------------------------------------
    |
    | copy_cut_max_size  - Max total size in MB for a single copy/cut operation.
    | copy_cut_max_count - Max number of files in a single copy/cut operation.
    |
    */
    'copy_cut_max_size' => 100,
    'copy_cut_max_count' => 200,

    /*
    |--------------------------------------------------------------------------
    | Allowed extensions
    |--------------------------------------------------------------------------
    |
    | Define which file types the user can upload and manage.
    | Files with extensions not listed here will be rejected.
    | The 'ext' key is auto-generated by merging all groups below.
    |
    | ext_img   - Image files.
    | ext_file  - Document files.
    | ext_video - Video files.
    | ext_music - Audio files.
    | ext_misc  - Archives and other files.
    |
    */
    'ext_img' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'webp'],
    'ext_file' => ['doc', 'docx', 'xls', 'xlsx', 'pdf', 'txt', 'log', 'xml', 'css', 'csv', 'json'],
    'ext_video' => ['mov', 'mpeg', 'm4v', 'mp4', 'avi', 'mpg', 'wma', 'flv', 'webm'],
    'ext_music' => ['mp3', 'mpga', 'm4a', 'ac3', 'aiff', 'mid', 'ogg', 'wav'],
    'ext_misc' => ['zip', 'rar', 'gz', 'tar'],

    /*
    |--------------------------------------------------------------------------
    | Extension blacklist
    |--------------------------------------------------------------------------
    |
    | Extensions that are ALWAYS blocked regardless of the allowed lists above.
    | This is a security measure to prevent uploading executable scripts.
    | Add any server-side executable extensions for your environment.
    |
    */
    'ext_blacklist' => [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        'cgi', 'pl', 'py', 'pyc', 'pyo',
        'sh', 'bash', 'zsh', 'ksh', 'csh',
        'exe', 'com', 'bat', 'cmd', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh', 'ps1', 'psm1',
        'msi', 'msp', 'mst', 'scr', 'pif', 'dll', 'sys', 'drv',
        'asp', 'aspx', 'ashx', 'asmx', 'cshtml', 'vbhtml',
        'jsp', 'jspx', 'cfm', 'cfc', 'shtml',
        'htaccess', 'htpasswd', 'ini', 'config', 'env',
        'rb', 'erb', 'go', 'rs', 'java', 'class', 'war', 'jar',
        'html', 'htm', 'xhtml', 'svg',
    ],

    /*
    |--------------------------------------------------------------------------
    | Hidden files and folders
    |--------------------------------------------------------------------------
    |
    | hidden_folders - Folder names that will not be shown in the listing.
    | hidden_files   - File names that will not be shown in the listing.
    |                  Supports exact names only (no wildcards).
    |
    */
    'hidden_folders' => [],
    'hidden_files' => ['config.php', '.rfm.config.json'],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | file_number_limit_js   - Max number of items to render in the browser
    |                          before showing a "too many files" warning.
    |                          Prevents browser slowdown on very large directories.
    | remember_text_filter   - Persist the search/filter text across page navigations
    |                          (stored in session).
    |
    */
    'file_number_limit_js' => 500,
    'remember_text_filter' => false,

    /*
    |--------------------------------------------------------------------------
    | Image Editor (Filerobot)
    |--------------------------------------------------------------------------
    |
    | image_editor_active   - Enable the built-in image editor (crop, resize,
    |                         filters, annotations). Uses Filerobot Image Editor.
    | image_editor_position - Where the editor opens: 'bottom' or 'overlay'.
    |
    */
    'image_editor_active' => true,
    'image_editor_position' => 'bottom',

    /*
    |--------------------------------------------------------------------------
    | Dark mode
    |--------------------------------------------------------------------------
    |
    | dark_mode - Enable dark mode support. When true, the file manager
    |             respects the user's OS preference and allows manual toggle.
    |
    */
    'dark_mode' => true,

    /*
    |--------------------------------------------------------------------------
    | TinyMCE header
    |--------------------------------------------------------------------------
    |
    | remove_header - Hide the file manager header toolbar when embedded
    |                 inside TinyMCE or CKEditor dialogs.
    |
    */
    'remove_header' => true,

    /*
    |--------------------------------------------------------------------------
    | Fixed thumbnail creation
    |--------------------------------------------------------------------------
    |
    | Automatically create additional image copies at fixed sizes on upload.
    | Useful for generating thumbnails at predefined dimensions.
    |
    | fixed_image_creation                 - Enable/disable this feature.
    | fixed_path_from_filemanager          - Target directories (relative to upload root).
    | fixed_image_creation_name_to_prepend - String prepended to the filename.
    | fixed_image_creation_to_append       - String appended to the filename (before ext).
    | fixed_image_creation_width           - Width in pixels for each target.
    | fixed_image_creation_height          - Height in pixels for each target.
    | fixed_image_creation_option          - Resize mode: 'auto', 'crop', 'exact',
    |                                        'portrait', 'landscape'.
    |
    | All arrays must have the same number of elements (one per target).
    |
    */
    'fixed_image_creation' => false,
    'fixed_path_from_filemanager' => ['/test/'],
    'fixed_image_creation_name_to_prepend' => ['test'],
    'fixed_image_creation_to_append' => ['prepand'],
    'fixed_image_creation_width' => [300],
    'fixed_image_creation_height' => [200],
    'fixed_image_creation_option' => ['crop', 'auto'],

    /*
    |--------------------------------------------------------------------------
    | Relative thumbnail creation
    |--------------------------------------------------------------------------
    |
    | Automatically create additional image copies relative to the current
    | upload folder. Similar to fixed creation but paths are relative.
    |
    | relative_image_creation                 - Enable/disable this feature.
    | relative_path_from_current_pos          - Target directories relative to the
    |                                           folder where the file is uploaded.
    | relative_image_creation_name_to_prepend - String prepended to the filename.
    | relative_image_creation_name_to_append  - String appended (before extension).
    | relative_image_creation_width           - Width in pixels for each target.
    | relative_image_creation_height          - Height in pixels for each target.
    | relative_image_creation_option          - Resize mode per target.
    |
    | All arrays must have the same number of elements (one per target).
    |
    */
    'relative_image_creation' => false,
    'relative_path_from_current_pos' => ['./', './'],
    'relative_image_creation_name_to_prepend' => [''],
    'relative_image_creation_name_to_append' => ['_thumb'],
    'relative_image_creation_width' => [300],
    'relative_image_creation_height' => [200],
    'relative_image_creation_option' => ['crop', 'crop'],

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    |
    | debug_error_message - When true, show detailed PHP error messages
    |                       in API responses. Set to false in production!
    |
    */
    'debug_error_message' => false,

    /*
    |--------------------------------------------------------------------------
    | CORS allowed origins
    |--------------------------------------------------------------------------
    |
    | cors_allowed_origins - Array of origins (e.g. 'https://example.com')
    |                        allowed to make cross-origin requests.
    |                        Same-origin requests are always allowed.
    |                        Leave empty to disallow all cross-origin requests.
    |
    */
    'cors_allowed_origins' => [],
];

// Merge all extension arrays into 'ext'
$config['ext'] = array_merge(
    $config['ext_img'],
    $config['ext_file'],
    $config['ext_misc'],
    $config['ext_video'],
    $config['ext_music'],
);

return $config;
