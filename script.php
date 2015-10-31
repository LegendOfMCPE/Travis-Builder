<?php
$deployBranch = !getenv("DEPLOY_BRANCH") ? getenv("TRAVIS_BRANCH") : getenv("DEPLOY_BRANCH");
$token = getenv("TOKEN");
putenv("TOKEN=''");
$pullRequest = getenv("TRAVIS_PULL_REQUEST") !== false;
$rootPath = rtrim(getenv("TRAVIS_BUILD_DIR"), "/");
    $rootPath = explode("/", $rootPath);
    array_pop($rootPath);
    $rootPath = implode("/", $rootPath) . "/";
    $pharPath = $rootPath . "build";
putenv("PHAR_PATH=$pharPath");
if($pullRequest){
    echo "[Info] 'Pull Request' detected, build will not be deployed.";
}else{
    echo "[Info] '$deployBranch' is the target Deploy-Branch";
}
if(!$token){
    echo "[Warning] No 'GitHub Token' provided, build will not be deployed.";
}

echo "[Info] Setting up environment...";
chdir("$rootPath");
exec("mkdir server");
exec("mkdir build");
chdir("server");
exec("mkdir plugins");
exec("cp " . getenv("TRAVIS_BUILD_DIR") . "/travis/TravisBuilder.php " . $rootPath . "server/plugins");
exec("cp " . getenv("TRAVIS_BUILD_DIR") . " " . $rootPath . "server/plugins/" . array_pop(explode("/", getenv("TRAVIS_REPO_SLUG"))));
exec("curl -sL get.pocketmine.net | bash -s - -v " . (getenv("PM_VERSION") !== false ? getenv("PM_VERSION") : "stable"));

echo "[Info] Starting PocketMine-MP...";
$server = proc_open(PHP_BINARY . "PocketMine-MP.phar --no-wizard --disable-readline", [
    0 => ["pipe" => "w"],
], $pipes);
while(!feof($pipes[0])){
    echo fgets($pipes[0]);
}
fclose($pipes[0]);
echo "[Info] PocketMine-MP stopped: " . proc_close($server);
if(count(glob($pharPath . "*.phar")) === 0){
    echo "[Error] Plugin PHAR was not created!";
    exit(1);
}
echo "[Info] Plugin PHAR successfully created!";

if(is_dir($pharPath) && !$pullRequest && $token !== false){
    echo "[Info] Preparing to deploy...";
    chdir("$pharPath");
    exec("git init");
    exec("git remote add origin https://$TOKEN@github.com/" . getenv("TRAVIS_REPO_SLUG"));
    exec("git fetch origin");
    exec("git config user.name \"iksaku's BuilderBot\"");
    exec("git config user.email \"iksaku_Bot@travis.ci\"");
    exec("git add .");
    echo "[Info] Creating commit...";
    exec("git commit -m \"New Build! Revision: " . getenv("TRAVIS_COMMIT") . "\"");
    echo "[Info] Pushing commit...";
    exec("git push --force --quiet origin HEAD:$deployBranch");
    echo "[Info] Build successfully uploaded!";
}

exit(0);