resource "aws_db_subnet_group" "irve" {
  name       = "irve-bdd"
  subnet_ids = module.vpc.private_subnets
}

resource "aws_security_group" "bdd" {
  name   = "irve-bdd"
  vpc_id = module.vpc.vpc_id

  ingress {
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [module.eks.node_security_group_id]
  }
}

resource "aws_db_instance" "irve" {
  identifier     = "irve-bdd"
  engine         = "mariadb"
  engine_version = "11.4"
  instance_class = "db.t4g.micro"

  allocated_storage = 20
  db_name  = "tv_fowet"
  username = "app_user"
  password = var.db_password

  db_subnet_group_name   = aws_db_subnet_group.irve.name
  vpc_security_group_ids = [aws_security_group.bdd.id]

  skip_final_snapshot = true
  publicly_accessible = false
}