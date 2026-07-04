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
    cloudflare = {
      source  = "cloudflare/cloudflare"
      version = "~> 5.0"
    }
  }

  # Values come from terraform/bootstrap's outputs (state_bucket_name,
  # lock_table_name) — backend blocks can't reference variables, so fill
  # these in by hand after running the bootstrap apply once.
  backend "s3" {
    bucket         = "streetbites-terraform-state"
    key            = "environments/prod/terraform.tfstate"
    region         = "us-east-1"
    dynamodb_table = "street-bites-terraform-locks"
    encrypt        = true
  }
}

# DNS for street-bites.org lives at Cloudflare, not Route 53. The provider
# authenticates via the CLOUDFLARE_API_TOKEN env var (a token scoped to
# Zone:Read + DNS:Edit on this one zone) — export it locally; CI supplies it
# as a repo *secret* (terraform-plan.yml / terraform-apply.yml). No argument
# here on purpose: the token must never land in state or a tfvars file.
provider "cloudflare" {}

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
