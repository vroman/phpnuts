<?php
/*
 * PHP-Nuts. A PHP package loader system.
 * Copyright (C) 2005 Víctor Román Archidona <contacto@victor-roman.es>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, see <http://www.gnu.org/licenses/>.
 */
class PHPNuts {
    private static $classpath = array (); /**< Our classpath     */
    private static $packages = array ();  /**< Loaded packages   */

    /*
     * \fn getLoadedPackages()
     * \brief Returns the currently loaded packages
     *
     * This functions returns (in an array) the current list of loaded
     * packages.
     */
    public static function getLoadedPackages() {
        return self::$packages;
    }

    /*
     * \fn setClassPath($path)
     * \brief Adds a path to PHP-Nuts search classpath.
     * 
     * \param $path Path to be added
     * 
     * We must set where PHP-Nuts must search for any package. This function
     * adds the specified path to do this mission.
     */
    public static function setClassPath($path) {
        $current_path = self::getClassPath();
        $new_path = (array) $path;

        self::$classpath = array_merge($current_path, $new_path);
    }

    /*
     * \fn getClassPath($as_array = true)
     * \brief Retuns the current classpath
     * \param $package Package to be loaded
     * \param $as Optionally variable which will contain the new instance (by reference).
     * \return An array with every path if first param is true
     * \return A line broken by ';' if the first param is false
     * 
     * This functions allows the programmer to get the current classpath
     * used by the class where it search the packages.
     */
    public static function getClassPath($as_array = true) {
        /*
         * Is possible to return our classpath in two different
         * flavors:
         *
         * By default: Returns an array
         * In a line.: In the same line returns the classpath, broken 
         *             by ';'.
         */
        if ($as_array == true) {
            return (array) self::$classpath;
        } else {
            $retval = implode(";", self::$classpath);
            return $retval;
        }
    }

    /*
     * \fn PackageLoad($package, &$as = NULL)
     * \brief Loads a package searching for it in the classpath
     * 
     * \param $package Package to be loaded
     * \param $as Optionally variable which will contain the new instance (by reference).
     * 
     * \return An instancie to a new object in the second parameter is specified
     * \return An instancie to a new object
     * 
     * This function loads the package and returns an instance to it if the
     * function was called with one parameter, or stores it into the second
     * parameter.
     * 
     * If is a superpackage (tld_domain.* or tld_domain.package.*), the function
     * returns an array with the instances as show:
     *   $array['tld.domain'] = Main package instance
     *   $array['tld.domain.package'] AND $array['package'] = Subpackage instance 
     */
    public static function Load($package, & $as = null) {
        if (self::isPackageLoaded($package)) {
            trigger_error("Package $package was previously loaded.", E_USER_WARNING);
            return null;
        }

        if ($package[strlen($package) - 1] != '*')
            return self::packageLoad($package, $as);
        else
            return self::packageLoadRecursive($package, $as);
    }

    /*
     * \fn isPackageLoaded($package)
     * \brief Checks if a package was previously loaded
     * 
     * \param $package Package to be checked
     * 
     * \return 0 If the package is not loaded
     * \return 1 If the package was loaded before
     * 
     * With isPackageLoaded we can check if the specified package was
     * loaded before with a 'Load' method call.
     */
    public static function isPackageLoaded($package) {
        $package = strtolower($package);
        return in_array($package, self::$packages);
    }

    /*
     * \fn packageAdd($package)
     * \brief Adds a package to internal package array list
     * 
     * \param $package Package to be added to our internal array list
     * 
     * After load a package, it MUST be added to the internal packages
     * list to skip problems loading the same package two or more times.
     */
    private static function packageAdd($package) {
        if (!in_array($package, self::$packages))
            self::$packages[] = strtolower($package);
    }

    /*
     * \fn PackageLoad($package, &$as = NULL)
     * \brief Loads a package searching for it in the classpath
     * \param $package Package to be loaded
     * \param $as Optionally variable which will contain the new instance (by reference).
     * \return An instancie to a new object in the second parameter is specified
     * \return An instancie to a new object
     * 
     * This function loads the package using one of the two available kinds
     * to do it:
     *   - The first is putting the class into tld/domain with the class name
     *     and adding .class.php (IE: tld/domain/ClassName/ClassName.class.php). The class
     *     will be caled "ClassName":
     *         class ClassName 
     *         {
     *           [code]
     *         }
     * 
     *   - The second is very similar. Puts the class into tld/domain and call
     *     it "ClassName.class.php" (tld/domain/ClassName/ClassName.class.php). BUT in
     *     his definition, it MUST me called tld_domain_classname. With this kind
     *     of call, avoid redefining classes is much more easiest.
     *         class tld_domain_ClassName 
     *         {
     *           [code]
     *         } 
     *  
     * Is transparent which kind had you used, this functions try to determine
     * it. 
     */
    private static function packageLoad($package, & $as = null) {
        /* The following variable is false until the class file is found */
        $found = false;

        /* 
         * Extracts the file name. With this filename also builds the
         * $classfile adding ".class.php";
         */
        $file = substr($package, strrpos($package, '.') + 1);
        $classfile = $file.".class.php";

        /* 
         * Now the directory to search. It is build in as shown below:
         *
         * With the package name: tld.domain.Package, first gets off
         * the "Package". This Package is the final filename with
         * .class.php added ("Package.class.php").
         *
         * The directory "tld/domain" is build replacing the '.' (dots)
         * with a '/' (slash) using str_replace.
         */
        $directory = substr($package, 0, -strlen($file) - 1);
        $directory = str_replace('.', '/', $directory);

        /*
         * Now we iterate over $classpath to search where the
         * directory will be. When the directory is found, try
         * to search for the class file, and sets $found to true
         * if the file is found.
         */
        foreach (self::getClassPath() as $cpath) {
            $cpath = $cpath.'/'.$directory.'/'.$file;
    
            if (is_dir($cpath)) {
                $classfile = $cpath.'/'.$classfile;
    
                if (is_file($classfile)) {
                    $found = true;
                    break;
                }
            }
        }

        /*
         * If the file was not found, advertise the user to correct
         * his classpath.
         */
        if ($found !== true) {
            trigger_error("Package $package not found on CLASSPATH", E_USER_ERROR);
        }

        /* Includes the file (only once) */
        include_once "$classfile";

        $classname = $file;

        /*
         * Checks if the class exists (based on the previous step). If it
         * not exists, warns the user.
         */
        if (!class_exists($classname)) {
            $new_classname = str_replace('/', '_', $directory).'_'.$file;

            /*
             * If "class ClassName" does not exists search for the other
             * possible construction "class tld_domain_classname". 
             */
            if (!class_exists($new_classname)) {
                trigger_error("Neither \"$classname\" nor \"$new_classname\" class exists on package $package", E_USER_ERROR);
            } else {
                /* Sets the fixed name into $classname */
                $classname = $new_classname;
            }
        }

        /* Adds the loaded package to internal array packages list */
        self::packageAdd($package);

        /*
         * This code determines how many args was the function called with. Is
         * necessary to determine what kind of operation do it, and will be of
         * two types:
         *
         * Only one argument: Object is returned with return an assigned to 
         *                    calling variable.
         * Two arguments: The variable in the second parameter is used to put
         *                (by reference) an instance to the class loaded.
         */
        $numargs = func_num_args();

        if ($numargs == 1)
            return new $classname;
        else
            (object) $as = new $classname;
    }

    /*
     * \fn PackageLoad($package, &$as = NULL)
     * \brief Loads a package searching for it in the classpath
     * 
     * \param $package Package to be loaded
     * \param $as Optionally variable which will contain the new instance (by reference).
     * 
     * \return An instancie to a new object in the second parameter is specified
     * \return An instancie to a new object
     * 
     * This function loads entire package and his subpackages into an array if
     * it was specified as second parameter, or returns them if only wass called
     * with one parameter.
     * 
     * The kind of resultant array is:
     * 
     *   $array['tld.domain.package'] AND $array['package'] = Subpackage instance 
     */
    private static function packageLoadRecursive($package, & $as) {
        /* Drops ".*" from the package name */
        $pkg = substr($package, 0, -2);

        /* Builds the "virtual" package path */
        $pkg_path = str_replace('.', '/', $pkg);

        foreach (self::$getClassPath() as $cpath) {
            /* Real path is "SEARCH_PATH/PACKAGE_PATH" */
            $real_path = $cpath.'/'.$pkg_path;

            /* 
             * If the search path not exists, continues the iteration with
             * the following classpath entry.
             * 
             * FIXME: If $real_path does not exists, we MUST NOT continue
             * without try to search the alternative path. This alternative
             * path is build taking the current path, and search for the
             * package here.
             */
            if (!is_dir($real_path))
                continue;

            /* Si el directorio existe, obtiene el listado de ficheros */
            $files = self::searchFilesToInclude($real_path);

            /* Try to load every packet one by one */
            foreach ($files as $file) {
                /* Drops the current search path */
                $to_load = substr($file, strlen($cpath) + 1);

                /* Drops ClassName.class.php */
                $to_load = substr($to_load, 0, strrpos($to_load, '/'));

                /* Replaces directories '/' with packages '.' */
                $to_load = str_replace('/', '.', $to_load);

                /* Convert to lower case */
                $to_load = strtolower($to_load);

                /* Now loads it */
                $pkg_name = substr($to_load, strlen($pkg) + 1);

                /*
                 * And finally builds the array. In first place we check
                 * if $pkg_name after drops exists. If it exists is a
                 * subpackage, and assings it to the array as show:
                 * 
                 *  $array['tld.domain.subpackage']; AND
                 *  $array['subpackage']
                 * 
                 * If $pkg_name does not exists is the base package, and
                 * assigns to the array as shows:
                 * 
                 *  $array['tld.domain']
                 */
                if ($pkg_name) {
                    self::packageLoad($to_load, $as[$pkg_name]);
                    $as[$pkg.'.'.$pkg_name] = $as[$pkg_name];
                } else {
                    self::packageLoad($to_load, $as[$pkg]);
                }
            } /* Foreach  files */
        } /* Foreach classpath */

        if ($as && (func_num_args() == 1))
            return $as;
    }

    /*
     * \fn getAvailablePackages()
     * \brief Gets the available packages searching them in the classpath.
     * 
     * \return An array with the available packages
     * \return NULL if there is not any package.
     * 
     * With getAvailablePackages we can get an array list with all available
     * packages. This function searchs for them in the classpath, and returns
     * the result or NULL if none is found.
     */
    public static function getAvailablePackages() {
        $retval = array ();

        foreach (self::getClassPath() as $cpath) {
            $cpath_len = strlen($cpath);

            foreach (self::searchFilesToInclude($cpath) as $file) {
                $file = substr($file, $cpath_len +1);
                $file = substr($file, 0, strrpos($file, '/'));
                $file = str_replace('/', '.', $file);

                $retval[] = $file;
            }
        }

        return count($retval) ? $retval : NULL;
    }

    /*
     * \fn Unload($package, &$as = NULL)
     * \brief Unloads a package cleaning his content
     * \param $as Object where the package was loaded
     * 
     * Destroys the variable fixing her value to NULL and doing an unset
     * after it.
     */
    public static function Unload(& $as) {
        $as = null;
        unset ($as);
    }

    /*
     * \fn searchFilesToInclude($parent, $autocall = false)
     * \brief Searchs files recursively to be loaded after.
     * 
     * \param $parent Main directory to search ("root directory")
     * \param $autocall Set it to true if you call this function from inside it.
     * 
     * \return An array with the files
     * \return NULL if he can found any file
     * 
     * This functions searchs on the specified parent and in all his subdirs
     * for "Package/Package.class.php". The param $autocall is a hack to
     * destroy the $ar_files array inside it, because if the function is
     * called two times it returns the result of the first execution PLUS
     * results of second execution.
     * 
     * In the very near future this function should be rewritten or entirely
     * drop, due is a problem origin.
     */
    private static function searchFilesToInclude($parent, $autocall = false) {
        /* Internal file array */
        static $ar_files = array ();

        /*
         * If the function is not called from it, we drop the previous
         * content stored in $ar_files. Read the function documentation
         * to know more about this.
         */
        if (!$autocall)
            $ar_files = array ();

        /*
         * If the specified directory is not a directory (yups), we
         * return NULL. With this isn't necessary check if the directory
         * handler is valid after. Also drops a possible error if the
         * directory not exists (without hide the warning with @). 
         */
        if (!is_dir($parent))
            return NULL;

        /* Opens the directory to be readed */
        $dh = opendir($parent);

        /* Extracts the base directory which class resides */
        $class_directory = substr($parent, strrpos($parent, '/') + 1);

        /* Full path to class file */
        $file = $parent.'/'.$class_directory.".class.php";

        /* Reads the current directory (with his subdirectories) */
        while (($current = readdir($dh)) !== false) {
            /*
             * Skips the current (.) and previous (..) directory to
             * avoid an infinite loop. 
             */
            if ($current == '.' || $current == "..")
                continue;

            /*
             * If the file exists and was not previously stored into our
             * internal files array, we store it.
             */
            if (is_file($file) && !in_array($file, $ar_files)) {
                $ar_files[] = $file;
                continue;
            }

            /*
             * Makes the recursive search. If the current data in $current is
             * a directory, we read it calling this funcion, and passing 'true'
             * as second parameter DUE THIS IS AN INTERNAL AUTOCALL.
             */
            if (is_dir($parent.'/'.$current))
                self::searchFilesToInclude($parent.'/'.$current, true);
        }

        /* Close the directory handler */
        closedir($dh);

        /* Returns an array with the full path to the files, or NULL */
        return count($ar_files) ? $ar_files : NULL;
    }
}
?>