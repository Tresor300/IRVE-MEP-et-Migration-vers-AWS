terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}

provider "aws" {
  region = "eu-west-3"
}
resource "aws_ecr_repository" "irve_app" {
  name = "irve-app"
}
output "repository_url" {
  value = aws_ecr_repository.irve_app.repository_url
}