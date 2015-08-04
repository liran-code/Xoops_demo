<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

/**
 * XOOPS User
 *
 * See the enclosed file license.txt for licensing information.
 * If you did not receive this file, get it at http://www.fsf.org/copyleft/gpl.html
 *
 * @copyright       The XOOPS Project http://sourceforge.net/projects/xoops/
 * @license         http://www.fsf.org/copyleft/gpl.html GNU General Public License (GPL)
 * @package         core
 * @since           2.0.0
 * @author          Kazumi Ono <webmaster@myweb.ne.jp>
 * @version         $Id: user.php 12083 2013-09-22 21:44:50Z rgriffith $
 */
include dirname(__FILE__) . DIRECTORY_SEPARATOR . 'mainfile.php';
$xoopsPreload =& XoopsPreload::getInstance();
$xoopsPreload->triggerEvent('core.user.start');

xoops_loadLanguage('user');

XoopsLoad::load('XoopsFilterInput');
$op = 'main';
if (isset($_POST['op'])) {
    // from $_POST we use keys: op, ok
    $op = trim(XoopsFilterInput::clean($_POST['op']));
    $clean_ok=false;
    if (isset($_POST['ok'])) {
        $clean_ok = XoopsFilterInput::clean($_POST['ok'], 'BOOLEAN');
    }
} elseif (isset($_GET['op'])) {
    // from $_GET we may use keys: op, xoops_redirect, id, actkey
    $op = trim(XoopsFilterInput::clean($_GET['op']));
    $clean_redirect = '';
    if (isset($_GET['xoops_redirect'])) {
        $clean_redirect = XoopsFilterInput::clean($_GET['xoops_redirect'], 'WEBURL');
    }
    if (isset($_GET['id'])) {
        $clean_id = XoopsFilterInput::clean($_GET['id'], 'INT');
    }
    if (isset($_GET['actkey'])) {
        $clean_actkey = XoopsFilterInput::clean($_GET['actkey'], 'STRING');
    }
}



if ($op == 'login') {
    include_once $GLOBALS['xoops']->path('include/checklogin.php');
    exit();
}

if ($op == 'main') {
    if (!$xoopsUser) {
        $xoopsOption['template_main'] = 'system_userform.html';
        include $GLOBALS['xoops']->path('header.php');
        $xoopsTpl->assign('xoops_pagetitle', _LOGIN);
        $xoTheme->addMeta('meta', 'keywords', _USERNAME . ", " . _US_PASSWORD . ", " . _US_LOSTPASSWORD);
        $xoTheme->addMeta('meta', 'description', _US_LOSTPASSWORD . " " . _US_NOPROBLEM);
        $xoopsTpl->assign('lang_login', _LOGIN);
        $xoopsTpl->assign('lang_username', _USERNAME);
        if (!empty($clean_redirect)) {
            $xoopsTpl->assign('redirect_page', htmlspecialchars(trim($clean_redirect), ENT_QUOTES));
        }
        if ($xoopsConfig['usercookie']) {
            $xoopsTpl->assign('lang_rememberme', _US_REMEMBERME);
        }
        $xoopsTpl->assign('lang_password', _PASSWORD);
        $xoopsTpl->assign('lang_notregister', _US_NOTREGISTERED);
        $xoopsTpl->assign('lang_lostpassword', _US_LOSTPASSWORD);
        $xoopsTpl->assign('lang_noproblem', _US_NOPROBLEM);
        $xoopsTpl->assign('lang_youremail', _US_YOUREMAIL);
        $xoopsTpl->assign('lang_sendpassword', _US_SENDPASSWORD);
        $xoopsTpl->assign('mailpasswd_token', $GLOBALS['xoopsSecurity']->createToken());
        include $GLOBALS['xoops']->path('footer.php');
        exit();
    }
    if (!empty($clean_redirect)) {
        $redirect = trim($clean_redirect);
        $isExternal = false;
        if ($pos = strpos($redirect, '://')) {
            $xoopsLocation = substr(XOOPS_URL, strpos(XOOPS_URL, '://') + 3);
            if (strcasecmp(substr($redirect, $pos + 3, strlen($xoopsLocation)), $xoopsLocation)) {
                $isExternal = true;
            }
        }
        if (! $isExternal) {
            header('Location: ' . $redirect);
            exit();
        }
    }
    header('Location: ' . XOOPS_URL . '/userinfo.php?uid=' . $xoopsUser->getVar('uid'));
    exit();
}

if ($op == 'logout') {
    $message = '';
    // Regenerate a new session id and destroy old session
    $GLOBALS["sess_handler"]->regenerate_id(true);
    $_SESSION = array();
    setcookie($xoopsConfig['usercookie'], 0, - 1, '/', XOOPS_COOKIE_DOMAIN, 0);
    setcookie($xoopsConfig['usercookie'], 0, - 1, '/');
    // clear entry from online users table
    if (is_object($xoopsUser)) {
        $online_handler =& xoops_gethandler('online');
        $online_handler->destroy($xoopsUser->getVar('uid'));
    }
    $message = _US_LOGGEDOUT . '<br />' . _US_THANKYOUFORVISIT;
    redirect_header('index.php', 1, $message);
    exit();
}

if ($op == 'actv') {
    $GLOBALS['xoopsLogger']->addDeprecated("Deprecated code. The activation is now handled by register.php");
    $id = isset($clean_id) ? $clean_id : 0;
    $actkey = isset($clean_actkey) ? $clean_actkey : '';
    redirect_header("register.php?id={$id}&amp;actkey={$actkey}", 1, '');
    exit();
}

if ($op == 'delete') {
    $config_handler =& xoops_gethandler('config');
    $xoopsConfigUser = $config_handler->getConfigsByCat(XOOPS_CONF_USER);
    if (!$xoopsUser || $xoopsConfigUser['self_delete'] != 1) {
        redirect_header('index.php', 5, _US_NOPERMISS);
        exit();
    } else {
        $groups = $xoopsUser->getGroups();
        if (in_array(XOOPS_GROUP_ADMIN, $groups)) {
            // users in the webmasters group may not be deleted
            redirect_header('user.php', 5, _US_ADMINNO);
            exit();
        }
        if (!$clean_ok) {
            include $GLOBALS['xoops']->path('header.php');
            xoops_confirm(
                array('op' => 'delete', 'ok' => 1),
                'user.php',
                _US_SURETODEL . '<br/>' . _US_REMOVEINFO
            );
            include $GLOBALS['xoops']->path('footer.php');
        } else {
            $del_uid = $xoopsUser->getVar("uid");
            $member_handler =& xoops_gethandler('member');
            if (false != $member_handler->deleteUser($xoopsUser)) {
                $online_handler =& xoops_gethandler('online');
                $online_handler->destroy($del_uid);
                xoops_notification_deletebyuser($del_uid);
                redirect_header('index.php', 5, _US_BEENDELED);
            }
            redirect_header('index.php', 5, _US_NOPERMISS);
        }
        exit();
    }
}
