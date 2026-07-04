variable "bucket_name" {
  description = "Globally-unique S3 bucket name. Backs the app's FILESYSTEM_PUBLIC_DISK=s3 disk (config/filesystems.php) — truck images and cached map PNGs."
  type        = string
}
