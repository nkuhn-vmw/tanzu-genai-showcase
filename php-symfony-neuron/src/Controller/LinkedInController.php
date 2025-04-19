<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\ExecutiveProfile;
use App\Service\LinkedInService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/linkedin')]
class LinkedInController extends AbstractController
{
    private LinkedInService $linkedInService;
    private EntityManagerInterface $entityManager;

    public function __construct(LinkedInService $linkedInService, EntityManagerInterface $entityManager)
    {
        $this->linkedInService = $linkedInService;
        $this->entityManager = $entityManager;
    }

    /**
     * Start the LinkedIn authentication process
     */
    #[Route('/auth/{executiveId}', name: 'linkedin_auth', methods: ['GET'])]
    public function auth(Request $request, ?int $executiveId = null): Response
    {
        // Store executive ID in session if provided
        if ($executiveId) {
            $request->getSession()->set('current_executive_id', $executiveId);
        }

        // Get authorization URL and redirect
        $authUrl = $this->linkedInService->getAuthorizationUrl();

        return $this->redirect($authUrl);
    }

    /**
     * Handle the LinkedIn OAuth callback
     */
    #[Route('/callback', name: 'linkedin_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        $error = $request->query->get('error');

        if ($error) {
            $this->addFlash('error', 'LinkedIn authentication failed: ' . $error);
            return $this->redirectToRoute('company_index');
        }

        if (!$code || !$state) {
            $this->addFlash('error', 'Invalid LinkedIn callback parameters');
            return $this->redirectToRoute('company_index');
        }

        // Handle the callback
        $result = $this->linkedInService->handleCallback($code, $state);

        if (!$result['success']) {
            $this->addFlash('error', 'LinkedIn authentication failed: ' . ($result['error'] ?? 'Unknown error'));
            return $this->redirectToRoute('company_index');
        }

        // Check if we need to link to an executive profile
        $executiveId = $request->getSession()->get('linkedin_auth_executive_id');
        if ($executiveId) {
            // Clear from session
            $request->getSession()->remove('linkedin_auth_executive_id');

            // Redirect to link page
            return $this->redirectToRoute('linkedin_link_profile', ['id' => $executiveId]);
        }

        $this->addFlash('success', 'Successfully authenticated with LinkedIn');

        // Redirect to profile page
        return $this->redirectToRoute('linkedin_profile');
    }

    /**
     * Show the current LinkedIn profile
     */
    #[Route('/profile', name: 'linkedin_profile', methods: ['GET'])]
    public function profile(): Response
    {
        if (!$this->linkedInService->hasValidToken()) {
            $this->addFlash('info', 'Please authenticate with LinkedIn first');
            return $this->redirectToRoute('company_index');
        }

        // Get profile data
        $profileResult = $this->linkedInService->getCurrentUserProfile();

        if (!$profileResult['success']) {
            $this->addFlash('error', 'Failed to get LinkedIn profile: ' . ($profileResult['error'] ?? 'Unknown error'));
            return $this->redirectToRoute('company_index');
        }

        // Get work experience
        $experienceResult = $this->linkedInService->getCurrentUserWorkExperience();
        $experiences = $experienceResult['success'] ? $experienceResult['experiences'] : [];

        // Get connections
        $connectionsResult = $this->linkedInService->getCurrentUserConnections();
        $connections = $connectionsResult['success'] ? $connectionsResult['connections'] : ['count' => 0, 'connections' => []];

        return $this->render('linkedin/profile.html.twig', [
            'profile' => $profileResult['profile'],
            'experiences' => $experiences,
            'connections' => $connections,
        ]);
    }

    /**
     * Link a LinkedIn profile to an executive profile
     */
    #[Route('/link/{id}', name: 'linkedin_link_profile', methods: ['GET', 'POST'])]
    public function linkProfile(ExecutiveProfile $executiveProfile, Request $request): Response
    {
        if (!$this->linkedInService->hasValidToken()) {
            $this->addFlash('info', 'Please authenticate with LinkedIn first');
            return $this->redirectToRoute('linkedin_auth', ['executiveId' => $executiveProfile->getId()]);
        }

        // If POST, process the link
        if ($request->isMethod('POST')) {
            $result = $this->linkedInService->syncExecutiveProfile($executiveProfile);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
                return $this->redirectToRoute('company_leadership', ['id' => $executiveProfile->getCompany()->getId()]);
            } else {
                $this->addFlash('error', $result['message']);
            }
        }

        // Get profile data for confirmation
        $profileResult = $this->linkedInService->getCurrentUserProfile();

        if (!$profileResult['success']) {
            $this->addFlash('error', 'Failed to get LinkedIn profile: ' . ($profileResult['error'] ?? 'Unknown error'));
            return $this->redirectToRoute('company_leadership', ['id' => $executiveProfile->getCompany()->getId()]);
        }

        return $this->render('linkedin/link_profile.html.twig', [
            'executive' => $executiveProfile,
            'linkedinProfile' => $profileResult['profile'],
        ]);
    }

    /**
     * View company network connections
     */
    #[Route('/company/{id}/network', name: 'linkedin_company_network', methods: ['GET'])]
    public function companyNetwork(Company $company): Response
    {
        // Get connection stats
        $connectionStats = $this->linkedInService->findCompanyConnections($company);

        return $this->render('linkedin/company_network.html.twig', [
            'company' => $company,
            'connectionStats' => $connectionStats,
            'executives' => $company->getExecutiveProfiles(),
        ]);
    }

    /**
     * View executive connections
     */
    #[Route('/executive/{id}/connections', name: 'linkedin_executive_connections', methods: ['GET'])]
    public function executiveConnections(ExecutiveProfile $executiveProfile): Response
    {
        if (!$executiveProfile->getLinkedinId()) {
            $this->addFlash('info', 'This executive profile is not linked to LinkedIn');
            return $this->redirectToRoute('company_leadership', ['id' => $executiveProfile->getCompany()->getId()]);
        }

        // Generate industry distribution for demo purposes
        // In a real implementation, this would come from LinkedIn data
        $industryDistribution = [
            'Technology' => rand(10, 30),
            'Finance' => rand(5, 20),
            'Healthcare' => rand(5, 15),
            'Manufacturing' => rand(5, 15),
            'Retail' => rand(3, 10),
            'Education' => rand(3, 10),
            'Other' => rand(5, 15),
        ];

        // Generate position distribution
        $positionDistribution = [
            'C-Level' => rand(5, 15),
            'VP' => rand(10, 20),
            'Director' => rand(15, 30),
            'Manager' => rand(20, 40),
            'Individual Contributor' => rand(10, 30),
        ];

        // Generate a mock list of key connections
        $keyConnections = [];
        $companies = ['Google', 'Amazon', 'Microsoft', 'Apple', 'Facebook', 'IBM', 'Intel', 'Oracle', 'SAP', 'Salesforce'];
        $titles = ['CEO', 'CTO', 'CFO', 'CMO', 'VP of Engineering', 'VP of Product', 'VP of Sales', 'Director of Marketing'];

        for ($i = 0; $i < 10; $i++) {
            $keyConnections[] = [
                'name' => 'Executive ' . ($i + 1),
                'title' => $titles[array_rand($titles)],
                'company' => $companies[array_rand($companies)],
                'connectionStrength' => rand(1, 3), // 1-3, where 3 is strongest
            ];
        }

        return $this->render('linkedin/executive_connections.html.twig', [
            'executive' => $executiveProfile,
            'company' => $executiveProfile->getCompany(),
            'connectionCount' => $executiveProfile->getConnectionCount() ?? rand(100, 500),
            'industryDistribution' => $industryDistribution,
            'positionDistribution' => $positionDistribution,
            'keyConnections' => $keyConnections,
        ]);
    }
}
