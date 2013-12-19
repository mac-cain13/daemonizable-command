# Changelog

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