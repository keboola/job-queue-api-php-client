# Job Queue API PHP Client [![Build Status](https://dev.azure.com/keboola-dev/job-queue-api-php-client/_apis/build/status/keboola.job-queue-api-php-client?branchName=main)](https://dev.azure.com/keboola-dev/job-queue-api-php-client/_build/latest?definitionId=66&branchName=main)

PHP client for the Job Queue API ([API docs](https://app.swaggerhub.com/apis-docs/keboola/job-queue-api/1.0.0)).

## Usage
```bash
composer require keboola/job-queue-api-php-client
```

```php
use Keboola\JobQueueClient\Client;
use Keboola\JobQueueClient\JobData;use Psr\Log\NullLogger;

$client = new Client(
    new NullLogger(),
    'http://queue.conenection.keboola.com/',
    'xxx-xxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
);
$result = $client->createJob(new JobData(
    'keboola.ex-db-snowflake',
    '123',
    [],
    'run'
));
var_dump($result['id']);
```

## Development
- Create a service principal to download the required images and login:

    ```bash
        SERVICE_PRINCIPAL_NAME=[USERNAME]-job-queue-internal-api-pull
        ACR_REGISTRY_ID=$(az acr show --name keboolapes --query id --output tsv --subscription c5182964-8dca-42c8-a77a-fa2a3c6946ea)
        SP_PASSWORD=$(az ad sp create-for-rbac --name http://$SERVICE_PRINCIPAL_NAME --scopes $ACR_REGISTRY_ID --role acrpull --query password --output tsv)
        SP_APP_ID=$(az ad sp show --id http://$SERVICE_PRINCIPAL_NAME --query appId --output tsv)    
    ```

- Login and check that you can pull the image:

    ```bash
        docker login keboolapes.azurecr.io --username $SP_APP_ID --password $SP_PASSWORD
        docker pull keboolapes.azurecr.io/job-queue-internal-api:latest
        docker pull keboolapes.azurecr.io/job-queue-api:latest
    ```

- Add the credentials to the k8s cluster:

    ```bash
        kubectl create secret docker-registry regcred --docker-server="https://keboolapes.azurecr.io" --docker-username="$SP_APP_ID" --docker-password="$SP_PASSWORD" --namespace dev-job-queue-api-php-client
        kubectl patch serviceaccount default -p "{\"imagePullSecrets\":[{\"name\":\"regcred\"}]}" --namespace dev-job-queue-api-php-client
    ```

- Set the following environment variables in `set-env.sh` file (use `set-env.template.sh` as sample):
    - `test_storage_api_url` - Keboola Connection URL.
    - `test_storage_api_token` - Token to a test project.

- Set one of Azure or AWS resources (or both, but only one is needed).  

### AWS Setup
- Create a user (`JobQueueApiPhpClient`) for local development using the `provisioning/aws.json` CF template. 
    - Create AWS key for the created user. 
    - Set the following environment variables in `set-env.sh` file (use `set-env.template.sh` as sample):
        - `test_aws_access_key_id` - The created security credentials for the `JobQueueApiPhpClient` user.
        - `test_aws_secret_access_key` - The created security credentials for the `JobQueueApiPhpClient` user.
        - `test_kms_region` - `Region` output of the above stack.
        - `test_kms_key_id` - `KmsKey` output of the above stack.

### Azure Setup

- Create a resource group:
    ```bash
    az account set --subscription "Keboola DEV PS Team CI"
    az group create --name testing-job-queue-api-php-client --location "East US"
    ```

- Create a service principal:
    ```bash
    az ad sp create-for-rbac --name testing-job-queue-api-php-client
    ```

- Use the response to set values `test_azure_client_id`, `test_azure_client_secret` and `test_azure_tenant_id` in the `set-env.sh` file:
    ```json 
    {
      "appId": "268a6f05-xxxxxxxxxxxxxxxxxxxxxxxxxxx", //-> test_azure_client_id
      "displayName": "testing-job-queue-internal-api-php-client",
      "name": "http://testing-job-queue-internal-api-php-client",
      "password": "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx", //-> test_azure_client_secret
      "tenant": "9b85ee6f-xxxxxxxxxxxxxxxxxxxxxxxxxxx" //-> test_azure_tenant_id
    }
    ```

- Get ID of the service principal:
    ```bash
    SERVICE_PRINCIPAL_ID=$(az ad sp list --display-name testing-job-queue-api-php-client --query "[0].objectId" --output tsv)
    ```
 
- Deploy the key vault, provide tenant ID, service principal ID and group ID from the previous commands:
    ```bash
    az deployment group create --resource-group testing-job-queue-api-php-client --template-file provisioning/azure.json --parameters vault_name=test-job-queue-client tenant_id=$test_azure_tenant_id service_principal_object_id=$SERVICE_PRINCIPAL_ID
    ```
  
- Show Key Vault URL
    ```bash
    az keyvault show --name test-job-queue-client --query "properties.vaultUri" --output tsv
    ```

returns e.g. `https://test-job-queue-client.vault.azure.net/`, use this to set values in `set-env.sh` file:
    - `test_azure_key_vault_url` - https://test-job-queue-client.vault.azure.net/

## Generate environment configuration

```bash
export db_password="cm9vdA==" #root
export db_password_urlencoded="cm9vdA=="
export test_azure_client_secret_base64=$(printf "%s" "$test_azure_client_secret"| base64 --wrap=0)
export test_aws_secret_access_key_base64=$(printf "%s" "$test_aws_secret_access_key"| base64 --wrap=0)

./set-env.sh
envsubst < provisioning/environments.yaml.template > provisioning/environments.yaml
kubectl apply -f ./provisioning/environments.yaml
kubectl apply -f ./provisioning/public-api.yaml
queue_public_api_ip=`kubectl get svc --output jsonpath --template "{.items[?(@.metadata.name==\"dev-job-queue-api-service\")].status.loadBalancer.ingress[].ip}" --namespace=dev-job-queue-api-php-client`

echo "queue_public_api_url: http://$(queue_public_api_ip):94"
```

Store the result `queue_public_api_ip` in `set-env.sh`.


## Run tests
- With the above setup, you can run tests:

    ```bash
    docker-compose build
    source ./set-env.sh && docker-compose run tests
    ```

- To run tests with local code use:

    ```bash
    docker-compose run tests-local composer install
    source ./set-env.sh && docker-compose run tests-local
    ```
