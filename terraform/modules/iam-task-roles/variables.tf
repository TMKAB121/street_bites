variable "project" {
  type = string
}

variable "s3_bucket_arn" {
  description = "The public storage bucket (modules/s3-public) the app's task role gets read/write access to — backs FILESYSTEM_PUBLIC_DISK=s3, so AWS_ACCESS_KEY_ID/SECRET stay blank in prod and the SDK picks up this role instead."
  type        = string
}

variable "secrets_arns" {
  description = "Secrets Manager ARNs the execution role may read (APP_KEY, DB password, Reverb credentials) to inject as task definition `secrets`."
  type        = list(string)
}
