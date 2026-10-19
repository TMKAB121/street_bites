# GitHub Actions authenticates via OIDC federation — no long-lived AWS access
# keys stored in GitHub Secrets. AWS validates the token against GitHub's own
# TLS chain; the thumbprint below is fetched dynamically rather than
# hardcoded so it tracks GitHub's certificate rotation automatically.

data "tls_certificate" "github" {
  url = "https://token.actions.githubusercontent.com"
}

# ACCOUNT-LEVEL AND SHARED. AWS allows exactly one provider for this URL per
# account, and the louisburg-community-choir, sayge_dev, and divapets repos all
# look it up with a `data "aws_iam_openid_connect_provider"` block
# (`create_github_oidc_provider = false`). It outlived the street_bites app
# (decommissioned 2026-10-18) for that reason. prevent_destroy guards against a
# `terraform destroy` here taking every sibling's CI down with it.
resource "aws_iam_openid_connect_provider" "github" {
  url             = "https://token.actions.githubusercontent.com"
  client_id_list  = ["sts.amazonaws.com"]
  thumbprint_list = [data.tls_certificate.github.certificates[0].sha1_fingerprint]

  lifecycle {
    prevent_destroy = true
  }
}
