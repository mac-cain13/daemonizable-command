# Changelog

**1.3.0:**
* Symfony 3 compatibility
* This release drops PHP 5.3 and 5.4 support

**1.2.4 - 1.2.6:**
* No breaking changes, see [release notes](https://github.com/mac-cain13/daemonizable-command/releases) for more info

**1.2.3:**
* Fixed return status code

**1.2.2:**
* Replaced deprecated getEntityManager() with getManager() to avoid warnings

**1.2.1:**
* Fixes in signal handling
* Added finishIteration() method
* Clearing entity cache after each iteration in EndlessContainerAwareCommand to prevent incorrectly cached results

**1.2.0:**

* **Replaced --verbose option with -q (quiet) option**
* Fixed: Crash on Symfony 2.3 and higher with the verbose option

**1.1.1:**

* Fixed: Run-once should not sleep after running the command

**1.1.0:**

* **Dropped Symfony 2.1.x and lower support**
* Fixed: Crash on Symfony 2.2 and higher

**1.0.1:**

* Fixed: Run-once should not sleep after running the command

**1.0.0:**

* First release