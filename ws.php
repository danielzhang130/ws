<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;

function get_return($param)
{
    $data = get_response($param)['account'];
    return floatval($data['moneyWeightedRate']['value']) * 100 . ',' . floatval($data['netLiquidationAmount']) . ',' .
        (floatval($data['netLiquidationAmount']) - floatval($data['totalDepositsAmount']) + floatval($data['totalWithdrawalsAmount']))
    ;
}

function get_response($param)
{
    require 'constants.php';

    $client = new Client([
        'base_uri' => 'https://my.wealthsimple.com'
    ]);

    $response = $client->post(
        '/graphql',
        [
            'json' => [
                'operationName' => 'getAccountDetails',
                'variables' => [
                    'accountId' => $param[1],
                    'clientId' => $param[0]
                ],
                'query' => 'query getAccountDetails($accountId: ID!, $clientId: ID!) { account(id: $accountId, client_id: $clientId) { ...PerformanceFields __typename }} fragment PerformanceFields on Account { netLiquidationAmount: net_liquidation_amount totalDepositsAmount: total_deposits_amount totalWithdrawalsAmount: total_withdrawals_amount ...AdvancedPerformanceFields  __typename } fragment AdvancedPerformanceFields on Account { moneyWeightedRate: rate_of_return(type: money_weighted, period: all_time) { value __typename } __typename}'
            ],
            'headers' => [
                'x-ws-profile' => 'invest',
                'Authorization' => "Bearer $app"
            ],
            'http_errors' => false
        ]
    );

    $status_code = $response->getStatusCode();
    $data = json_decode($response->getBody()->getContents(), true);

    if ($status_code != 200) {
        if ($status_code == 401) {
            refresh();
            $data = get_response($param);
        } else {
            throw new RuntimeException($status_code . $response->getBody()->getContents());
        }
    }
    if (isset($data['data']))
    {
        return $data['data'];
    }
    else if (isset($data['account']))
    {
        return $data;
    }
    else
    {
        var_dump($data);
        throw new RuntimeException();
    }
}

function refresh()
{
    require 'constants.php';

    $client = new Client([
        'base_uri' => 'https://api.production.wealthsimple.com/'
    ]);

    $response = $client->post(
        "/v1/oauth/v2/token",
        [
            'json' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh,
                'client_id' => $key
            ],
            'headers' => [
                'authorization' => "Bearer $app"
            ],
            'http_errors' => false
        ]
    );

    $status_code = $response->getStatusCode();

    if ($status_code != 200) {
        throw new RuntimeException($status_code . $response->getBody()->getContents());
    }

    $data = json_decode($response->getBody()->getContents(), true);

    $refresh_token = $data['refresh_token'];
    $access_token = $data['access_token'];

    $file = "<?php \$key = '$key';\n\$refresh = '$refresh_token'; \n\$app = '$access_token';\n?>\n";
    file_put_contents('constants.php', $file);
}

function token()
{
    require 'constants.php';
    require 'config.php';

    echo "pass";
    $pass = trim(fgets(STDIN));
    echo "otp";
    $otp = trim(fgets(STDIN));
    echo "got: $otp";
    
    $client = new Client([
        'base_uri' => 'https://api.production.wealthsimple.com/'
    ]);

    $response = $client->post(
        "/v1/oauth/v2/token",
        [
            'json' => [
                'grant_type' => 'password',
                'username' => $username,
                'password' => $pass,
                'skip_provision' => true,
                'scope' => 'invest.read mfda.read mercer.read',
                'client_id' => $key
            ],
            'headers' => [
                'x-wealthsimple-otp' => $otp
            ],
            'http_errors' => false
        ]
    );

    $status_code = $response->getStatusCode();

    if ($status_code != 200) {
        throw new RuntimeException($status_code . $response->getBody()->getContents());
    }

    $data = json_decode($response->getBody()->getContents(), true);

    $refresh_token = $data['refresh_token'];
    $access_token = $data['access_token'];

    $file = "<?php \$key = '$key';\n\$refresh = '$refresh_token'; \n\$app = '$access_token';\n?>\n";
    file_put_contents('constants.php', $file);
}

require 'config.php';

if ($argc == 2 && $argv[1] == 'token')
{
    token();
}
else
{
    $time = time();

    foreach ($params as $param)
    {
        $file = fopen("$out/${param[0]}-${param[1]}.csv", 'a');
        fwrite($file, date('j n Y', $time) . ',' . get_return($param));
        fwrite($file, PHP_EOL);
        fclose($file);
    }
}
?>
