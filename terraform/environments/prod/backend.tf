terraform {
  required_version = ">= 1.5.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.6"
    }
  }

  # Values come from terraform/bootstrap's outputs (state_bucket_name,
  # lock_table_name) — backend blocks can't reference variables, so fill
  # these in by hand after running the bootstrap apply once.
  backend "s3" {
    bucket         = "REPLACE-WITH-bootstrap-output-state_bucket_name"
    key            = "environments/prod/terraform.tfstate"
    region         = "us-east-1"
    dynamodb_table = "REPLACE-WITH-bootstrap-output-lock_table_name"
    encrypt        = true
  }
}

provider "aws" {
  region = var.aws_region

  default_tags {
    tags = {
      Project     = var.project
      ManagedBy   = "terraform"
      Environment = "prod"
    }
  }
}
