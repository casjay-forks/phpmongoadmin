<?php

namespace Controllers;

use Capsule\Response;
use Responses\JsonResponse;
use Helpers\MongoDBHelper as MongoDB;
use Normalizers\ErrorNormalizer;

class CollectionsController extends Controller {

    public function manage() : Response {

        AuthController::ensureUserIsLogged();
        
        return new Response(200, $this->renderView('manageCollections', [
            'databaseNames' => DatabasesController::getDatabaseNames()
        ]));

    }

    /**
     * @see https://docs.mongodb.com/php-library/v1.6/reference/method/MongoDBDatabase-listCollections/index.html
     */
    public function list() : JsonResponse {

        try {
            $decodedRequestBody = $this->getDecodedRequestBody();
        } catch (\Throwable $th) {
            return new JsonResponse(400, ErrorNormalizer::normalize($th, __METHOD__));
        }

        try {

            $database = MongoDB::getClient()->selectDatabase(
                $decodedRequestBody['databaseName']
            );

            $collectionNames = [];

            foreach ($database->listCollections() as $collectionInfo) {
                $collectionNames[] = $collectionInfo['name'];
            }

        } catch (\Throwable $th) {
            return new JsonResponse(500, ErrorNormalizer::normalize($th, __METHOD__));
        }

        sort($collectionNames);

        return new JsonResponse(200, $collectionNames);

    }

    /**
     * @see https://docs.mongodb.com/php-library/v1.6/reference/method/MongoDBDatabase-createCollection/index.html
     */
    public function create() : JsonResponse {

        try {
            $decodedRequestBody = $this->getDecodedRequestBody();
        } catch (\Throwable $th) {
            return new JsonResponse(400, ErrorNormalizer::normalize($th, __METHOD__));
        }

        try {

            $database = MongoDB::getClient()->selectDatabase(
                $decodedRequestBody['databaseName']
            );

            // TODO: Check createCollection result?
            $database->createCollection($decodedRequestBody['collectionName']);

        } catch (\Throwable $th) {
            return new JsonResponse(500, ErrorNormalizer::normalize($th, __METHOD__));
        }
        
        return new JsonResponse(200, true);

    }

    /**
     * @see https://docs.mongodb.com/manual/reference/command/renameCollection/
     */
    public function rename() : JsonResponse {

        try {
            $decodedRequestBody = $this->getDecodedRequestBody();
        } catch (\Throwable $th) {
            return new JsonResponse(400, ErrorNormalizer::normalize($th, __METHOD__));
        }

        try {

            $database = MongoDB::getClient()->selectDatabase('admin');

            // TODO: Check command result?
            $database->command([
                'renameCollection' => $decodedRequestBody['databaseName'] . '.'
                    . $decodedRequestBody['oldCollectionName'],
                'to' => $decodedRequestBody['databaseName'] . '.'
                    . $decodedRequestBody['newCollectionName']
            ]);

        } catch (\Throwable $th) {
            return new JsonResponse(500, ErrorNormalizer::normalize($th, __METHOD__));
        }

        return new JsonResponse(200, true);

    }

    /**
     * @see https://docs.mongodb.com/php-library/v1.6/reference/method/MongoDBCollection-drop/index.html
     */
    public function drop() : JsonResponse {

        try {
            $decodedRequestBody = $this->getDecodedRequestBody();
        } catch (\Throwable $th) {
            return new JsonResponse(400, ErrorNormalizer::normalize($th, __METHOD__));
        }

        try {

            $collection = MongoDB::getClient()->selectCollection(
                $decodedRequestBody['databaseName'], $decodedRequestBody['collectionName']
            );

            // TODO: Check drop result?
            $collection->drop();

        } catch (\Throwable $th) {
            return new JsonResponse(500, ErrorNormalizer::normalize($th, __METHOD__));
        }

        return new JsonResponse(200, true);

    }

    public function enumFields() : JsonResponse {

        try {
            $decodedRequestBody = $this->getDecodedRequestBody();
        } catch (\Throwable $th) {
            return new JsonResponse(400, ErrorNormalizer::normalize($th, __METHOD__));
        }

        try {

            $collection = MongoDB::getClient()->selectCollection(
                $decodedRequestBody['databaseName'], $decodedRequestBody['collectionName']
            );

            $documents = $collection->find([], ['limit' => 1])->toArray();

        } catch (\Throwable $th) {
            return new JsonResponse(500, ErrorNormalizer::normalize($th, __METHOD__));
        }

        if ( empty($documents) ) {
            return new JsonResponse(200, []);
        }

        $document = $documents[0]->jsonSerialize();

        if ( property_exists($document, '_id')
            && is_a($document->_id, '\MongoDB\BSON\ObjectId') ) {
                $document->_id = (string) $document->_id;
        }

        DocumentsController::formatRecursively($document);

        $array = json_decode(json_encode($document), JSON_OBJECT_AS_ARRAY);

        /**
         * Converts multidimensional array to 2D array with dot notation keys.
         * @see https://stackoverflow.com/questions/10424335/php-convert-multidimensional-array-to-2d-array-with-dot-notation-keys
         */
        $ritit = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array));
        $result = [];

        foreach ($ritit as $unusedValue) {
            $keys = [];
            foreach (range(0, $ritit->getDepth()) as $depth) {
                $keys[] = $ritit->getSubIterator($depth)->key();
            }
            $result[] = join('.', $keys);
        }

        $documentFields = array_unique($result);

        return new JsonResponse(200, $documentFields);

    }

}
