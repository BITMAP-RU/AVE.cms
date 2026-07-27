<?php

	/*
	|--------------------------------------------------------------------------------------
	| AVE.cms
	|--------------------------------------------------------------------------------------
	| @package      AVE.cms
	| @file         system/App/Frontend/DocumentApi/Controller.php
	| @author       AVE.cms <support@ave-cms.ru>
	| @copyright    2007-2026 (c) AVE.cms
	| @link         https://ave-cms.ru
	| @version      3.3
	*/

	namespace App\Frontend\DocumentApi;

	defined('BASEPATH') || die('Direct access to this location is not allowed.');

	use App\Common\ApiTokenRepository;
	use App\Common\ErrorReport;
	use App\Common\RateLimiter;
	use App\Content\ContentTables;
	use App\Content\Documents\DocumentMutationService;
	use App\Content\Documents\DocumentSaveRejected;
	use App\Content\Documents\DocumentSnapshotRepository;
	use App\Helpers\Request;
	use App\Helpers\Response;
	use DB;

	class Controller
	{
		public function show(array $params = array())
		{
			$token = $this->authorize('documents:read');
			$id = isset($params['id']) ? (int) $params['id'] : 0;
			return $this->respondDocument($id);
		}

		public function byAlias(array $params = array())
		{
			$token = $this->authorize('documents:read');
			$alias = trim(Request::getStr('alias', ''), '/');
			$id = $alias !== '' ? (int) DB::query('SELECT Id FROM ' . ContentTables::table('documents') . ' WHERE document_alias=%s AND document_deleted=0 LIMIT 1', $alias)->getValue() : 0;
			return $this->respondDocument($id);
		}

		public function store(array $params = array())
		{
			$token = $this->authorize('documents:write');
			return $this->mutate(0, $token, 201);
		}

		public function update(array $params = array())
		{
			$token = $this->authorize('documents:write');
			return $this->mutate(isset($params['id']) ? (int) $params['id'] : 0, $token, 200);
		}

		protected function respondDocument($id)
		{
			$snapshot = (new DocumentSnapshotRepository())->find((int) $id);
			if (!is_array($snapshot)) { return Response::json(array('success'=>false,'error'=>array('code'=>'not_found','message'=>'Документ не найден')), 404); }
			return Response::json(array('success'=>true,'data'=>(new DocumentMutationService())->export($snapshot)), 200);
		}

		protected function mutate($id, array $token, $successStatus)
		{
			$contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower((string) $_SERVER['CONTENT_TYPE']) : '';
			if (strpos($contentType, 'application/json') !== 0) { return Response::json(array('success'=>false,'error'=>array('code'=>'unsupported_media_type','message'=>'Используйте Content-Type: application/json')), 415); }
			$raw = file_get_contents('php://input');
			if ($raw === false || strlen($raw) > 2097152) { return Response::json(array('success'=>false,'error'=>array('code'=>'payload_too_large','message'=>'JSON не должен превышать 2 МБ')), 413); }
			$payload = json_decode($raw, true);
			if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) { return Response::json(array('success'=>false,'error'=>array('code'=>'invalid_json','message'=>'Передан некорректный JSON')), 400); }
			try {
				$result = (new DocumentMutationService())->save((int) $id, $payload, (int) $token['user_id'], 'api');
				return Response::json(array('success'=>true,'data'=>(new DocumentMutationService())->export($result['snapshot']),'meta'=>array('operation'=>$result['operation'],'snapshot_warning'=>$result['snapshot_warning'])), $successStatus);
			} catch (DocumentSaveRejected $e) {
				return Response::json(array('success'=>false,'error'=>array('code'=>'validation_failed','message'=>$e->getMessage(),'fields'=>$e->errors())), 422);
			} catch (\Throwable $e) {
				$message = ErrorReport::publicMessage('Не удалось сохранить документ', $e, 'DOCAPI', array('document_id'=>(int)$id));
				return Response::json(array('success'=>false,'error'=>array('code'=>'save_failed','message'=>$message)), 500);
			}
		}

		protected function authorize($scope)
		{
			$token = ApiTokenRepository::authenticate($scope);
			if (!$token) { Response::json(array('success'=>false,'error'=>array('code'=>'unauthorized','message'=>'API-токен отсутствует, недействителен или не имеет нужного scope')), 401); }
			$key = 'document-api:' . (int) $token['id'];
			if (!RateLimiter::attempt($key, 120, 60)) {
				header('Retry-After: ' . RateLimiter::availableIn($key));
				Response::json(array('success'=>false,'error'=>array('code'=>'rate_limited','message'=>'Превышен лимит API-запросов')), 429);
			}

			return $token;
		}
	}
