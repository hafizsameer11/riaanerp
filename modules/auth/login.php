<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Sautech Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta content="Login page" name="description" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <!-- App favicon -->
    <link rel="shortcut icon" href="assets/images/favicon.ico">

    <!-- App css -->
    <link href="../../assets/admin/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="../../assets/admin/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="../../assets/admin/css/app.min.css" rel="stylesheet" type="text/css" />
</head>

<body class="account-body accountbg">
    <!-- Login page -->
    <div class="container">
        <div class="row vh-100 d-flex justify-content-center">
            <div class="col-12 align-self-center">
                <div class="row">
                    <div class="col-lg-5 mx-auto">
                        <div class="card">
                            <div class="card-body p-0 " style="background: #1e2a38;">
                                <div class="text-center p-3">
                                    <a href="index.html" class="logo logo-admin">
                                        <img src="../../assets/img/logofinal.png" style="width: 220px; height: auto;object-fit:contain;border-radius: 100%;" alt="logo" class="auth-logo">
                                    </a>
                                    <!-- <h4 class=" mb-1 font-weight-semibold text-white font-18">Sautech</h4> -->
                                </div>
                            </div>
                            <div class="card-body">

                                <!-- Login Form -->
                                <form class="form-horizontal auth-form my-4" action="login-backend.php" method="POST" >

                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <div class="input-group mb-3">
                                            <input type="text" class="form-control" name="username" id="username" placeholder="Enter username">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="userpassword">Password</label>
                                        <div class="input-group mb-3">
                                            <input type="password" class="form-control" name="password" id="userpassword" placeholder="Enter password">
                                        </div>
                                    </div>

                                    <div class="form-group row mt-4">
                                        <div class="col-sm-6">
                                            <div class="custom-control custom-switch switch-success">
                                                <input type="checkbox" class="custom-control-input" id="customSwitchSuccess2">
                                                <label class="custom-control-label text-muted" for="customSwitchSuccess2">Remember me</label>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 text-right">
                                            <a href="#" class="text-muted font-13">
                                                <i class="dripicons-lock"></i> Forgot password?
                                            </a>
                                        </div>
                                    </div>

                                    <div class="form-group mb-0 row">
                                        <div class="col-12 mt-2">
                                            <button class="btn btn-block waves-effect waves-light" style="background: #1e2a38;color:white;" type="submit">
                                                Log In <i class="fas fa-sign-in-alt ml-1"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>

                            </div> <!-- end card-body -->

                            <!-- <div class="card-body bg-light-alt text-center">
                                <span class="text-muted d-none d-sm-inline-block">©<?php echo date('Y') ?></span>                                            
                            </div> -->

                        </div> <!-- end card -->
                    </div> <!-- end col -->
                </div> <!-- end row -->
            </div> <!-- end col -->
        </div> <!-- end row -->
    </div> <!-- end container -->

    <!-- Scripts -->
    <script src="../../assets/admin/js/jquery.min.js"></script>
    <script src="../../assets/admin/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/admin/js/waves.js"></script>
    <script src="../../assets/admin/js/feather.min.js"></script>
    <script src="../../assets/admin/js/simplebar.min.js"></script>

</body>

</html>
