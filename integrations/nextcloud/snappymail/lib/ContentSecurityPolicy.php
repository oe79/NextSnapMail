<?php

namespace OCA\SnappyMail;

use OCP\AppFramework\Http\ContentSecurityPolicy as NextcloudContentSecurityPolicy;

/**
 * Builds a Nextcloud content security policy without inheriting from its
 * internal representation. This keeps SnappyMail isolated from CSP property
 * changes between Nextcloud releases.
 */
class ContentSecurityPolicy
{
	private NextcloudContentSecurityPolicy $policy;
	private string $nonce;

	public function __construct()
	{
		$this->policy = new NextcloudContentSecurityPolicy();
		$snappyMailPolicy = \RainLoop\Api::getCSP();

		foreach ($snappyMailPolicy->get('script-src') as $domain) {
			// Knockout's legacy binding parser still evaluates binding strings.
			// Keep unsafe-eval until those bindings have been migrated.
			if ("'unsafe-inline'" !== $domain) {
				$this->policy->addAllowedScriptDomain($domain);
			}
		}

		if (\method_exists($this->policy, 'useStrictDynamic')) {
			$this->policy->useStrictDynamic(true);
		} else {
			$this->policy->addAllowedScriptDomain("'strict-dynamic'");
		}

		foreach ($snappyMailPolicy->get('img-src') as $domain) {
			$this->policy->addAllowedImageDomain($domain);
		}

		foreach ($snappyMailPolicy->get('style-src') as $domain) {
			if ("'unsafe-inline'" !== $domain) {
				$this->policy->addAllowedStyleDomain($domain);
			}
		}

		foreach ($snappyMailPolicy->get('frame-src') as $domain) {
			$this->policy->addAllowedFrameDomain($domain);
		}

		if (\method_exists($this->policy, 'addReportTo')) {
			foreach ($snappyMailPolicy->report_to as $reportTo) {
				$this->policy->addReportTo($reportTo);
			}
		}

		$this->nonce = \SnappyMail\UUID::generate();
		$this->policy->addAllowedScriptDomain("'nonce-{$this->nonce}'");
	}

	public function addAllowedFrameDomain(string $domain): void
	{
		$this->policy->addAllowedFrameDomain($domain);
	}

	public function getSnappyMailNonce(): string
	{
		return $this->nonce;
	}

	public function getPolicy(): NextcloudContentSecurityPolicy
	{
		return $this->policy;
	}
}
