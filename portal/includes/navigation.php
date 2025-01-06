<?php if($PAGE != "login.php") { ?>
    <body class="hold-transition sidebar-collapse sidebar-mini layout-fixed">
        <style>
            .code-block {
                background-color: #f5f5f5;
                border-left: 3px solid #9b4dca;
                padding: 10px;
                margin: 10px 0;
                font-family: monospace;
                white-space: pre-wrap;
                word-wrap: break-word;
            }

            html, body {
                height: 100%;
                margin: 0;
            }

            .wrapper {
                display: flex;
                flex-direction: column;
                min-height: 100vh;
            }

            .content-wrapper {
                flex: 1;
                padding-bottom: 50px;
            }

            .footer {
                width: 100%;
                height: 50px;
                background-color: #f8f9fa;
                text-align: center;
            }
        </style>

        <div class="wrapper">
            <!-- Preloader -->
            <div class="preloader flex-column justify-content-center align-items-center">
                <span class="brand-text font-weight-light">Loading...</span>
            </div>

            <!-- Navbar -->
            <nav class="main-header navbar navbar-expand navbar-white navbar-light">
                <!-- Left navbar links -->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                            <i class="fas fa-bars"></i>
                        </a>
                    </li>
                </ul>

                <!-- Right navbar links -->
                <ul class="navbar-nav ml-auto">
                    <!-- Search -->
                    <li class="nav-item">
                        <a class="nav-link" data-widget="navbar-search" href="#" role="button">
                            <i class="fas fa-search"></i>
                        </a>
                        <div class="navbar-search-block">
                            <form class="form-inline" id="search_form">
                                <div class="input-group input-group-sm">
                                    <input class="form-control form-control-navbar" 
                                           name="search_word" 
                                           id="search_word" 
                                           type="search" 
                                           placeholder="Search" 
                                           aria-label="Search">
                                    <div class="input-group-append">
                                        <button class="btn btn-navbar" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <button class="btn btn-navbar" 
                                                type="button" 
                                                data-widget="navbar-search">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </li>

                    <!-- User Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link" data-toggle="dropdown" href="#">
                            <p><?php echo $uswin; ?></p>
                        </a>
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                            <span class="dropdown-item dropdown-header">
                                <div class="dropdown-divider"></div>
                                <img src="https://profilepicture.sampleorganization.com/apps/photoapp/ImageServlet?eid=<?php echo $_SESSION[$APP."_user_num"]; ?>"
                                     class="img-circle elevation-2" 
                                     alt="User Image">
                                <div class="dropdown-divider"></div>
                            </span>
                            <a href="logout.php" class="dropdown-footer btn-primary">SIGN OUT</a>
                        </div>
                    </li>
                </ul>
            </nav>

            <!-- Main Sidebar -->
            <aside class="main-sidebar sidebar-dark-primary elevation-4">
                <a href="" class="brand-link">
                    <span class="brand-text font-weight-light"><?php echo ucfirst($APP); ?> NMC</span>
                </a>

                <div class="sidebar">
                    <!-- User Panel -->
                    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                        <div class="image">
                            <img src="https://profilepicture.sampleorganization.com/apps/photoapp/ImageServlet?eid=<?php echo $_SESSION[$APP."_user_num"]; ?>"
                                 class="img-circle elevation-2" 
                                 alt="User Image">
                        </div>
                        <div class="info">
                            <a href="https://oneprofile.sampleorganization.com/profile/<?php echo $_SESSION[$APP."_user_num"]; ?>"
                               target="_blank" 
                               class="d-block"><?php echo "$fname $lname"; ?></a>
                        </div>
                    </div>

                    <!-- Sidebar Menu -->
                    <nav class="mt-2">
                        <ul class="nav nav-pills nav-sidebar flex-column" 
                            data-widget="treeview" 
                            role="menu" 
                            data-accordion="false">
                            <?php
                            foreach ($data as $key => $value) {
                                // Skip metadata fields
                                if ($key === 'description' || $key === 'summary') {
                                    continue;
                                }

                                // Validate menu item structure
                                if (!is_array($value) || !isset($value['type']) || !isset($value['urls'])) {
                                    continue;
                                }

                                // Handle single menu items
                                if ($value['type'] === 'single' && !empty($value['urls'])) {
                                    foreach ($value['urls'] as $title => $info) {
                                        $displayLink = false;
                                        foreach ($adom_groups as $userRole) {
                                            $userRole = str_replace("'", "", $userRole);
                                            foreach ($info['roles'] as $role) {
                                                if ($role == $userRole) {
                                                    $displayLink = true;
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        if ($displayLink) {
                                            ?>
                                            <li class="nav-item">
                                                <a href="<?php echo $info['url']; ?>" 
                                                   class="nav-link <?php if ($URI == $info['url']) echo 'active'; ?>">
                                                    <i class="nav-icon <?php echo $value['img']; ?>"></i>
                                                    <p><?php echo $title; ?></p>
                                                </a>
                                            </li>
                                            <?php
                                        }
                                    }
                                }
                                // Handle category menu items
                                else if ($value['type'] === 'category' && !empty($value['urls'])) {
                                    $category_authorized = false;
                                    foreach ($value['urls'] as $title => $info) {
                                        $displayLink = false;
                                        foreach ($adom_groups as $userRole) {
                                            $userRole = str_replace("'", "", $userRole);
                                            foreach ($info['roles'] as $role) {
                                                if ($role == $userRole) {
                                                    $category_authorized = true;
                                                    break 2;
                                                }
                                            }
                                        }
                                    }

                                    if ($category_authorized) {
                                        ?>
                                        <li class="nav-item">
                                            <a href="#" class="nav-link">
                                                <i class="nav-icon <?php echo htmlspecialchars($value['img'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                                <p>
                                                    <?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>
                                                    <i class="right fas fa-angle-left"></i>
                                                </p>
                                            </a>
                                            <ul class="nav nav-treeview">
                                                <?php
                                                foreach ($value['urls'] as $title => $info) {
                                                    $displayLink = false;
                                                    foreach ($adom_groups as $userRole) {
                                                        $userRole = str_replace("'", "", $userRole);
                                                        foreach ($info['roles'] as $role) {
                                                            if ($role == $userRole) {
                                                                $displayLink = true;
                                                                break;
                                                            }
                                                        }
                                                    }

                                                    if ($displayLink) {
                                                        ?>
                                                        <li class="nav-item">
                                                            <a href="<?php echo $info['url']; ?>" class="nav-link">
                                                                <i class="far fa-circle nav-icon"></i>
                                                                <p><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></p>
                                                            </a>
                                                        </li>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </ul>
                                        </li>
                                        <?php
                                    }
                                }
                            }
                            ?>
                        </ul>
                    </nav>
                    <!-- End of Sidebar Menu -->
                </div>
                <!-- End of Sidebar -->
            </aside>
        <?php } ?>
