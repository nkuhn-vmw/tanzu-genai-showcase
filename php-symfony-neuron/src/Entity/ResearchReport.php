<?php

namespace App\Entity;

use App\Repository\ResearchReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResearchReportRepository::class)]
class ResearchReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'researchReports')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $executiveSummary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $companyOverview = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $industryAnalysis = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $financialAnalysis = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $competitiveAnalysis = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $swotAnalysis = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $investmentHighlights = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $risksAndChallenges = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conclusion = null;

    #[ORM\Column(length: 50)]
    private ?string $reportType = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $generatedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getExecutiveSummary(): ?string
    {
        return $this->executiveSummary;
    }

    public function setExecutiveSummary(?string $executiveSummary): static
    {
        $this->executiveSummary = $executiveSummary;

        return $this;
    }

    public function getCompanyOverview(): ?string
    {
        return $this->companyOverview;
    }

    public function setCompanyOverview(?string $companyOverview): static
    {
        $this->companyOverview = $companyOverview;

        return $this;
    }

    public function getIndustryAnalysis(): ?string
    {
        return $this->industryAnalysis;
    }

    public function setIndustryAnalysis(?string $industryAnalysis): static
    {
        $this->industryAnalysis = $industryAnalysis;

        return $this;
    }

    public function getFinancialAnalysis(): ?string
    {
        return $this->financialAnalysis;
    }

    public function setFinancialAnalysis(?string $financialAnalysis): static
    {
        $this->financialAnalysis = $financialAnalysis;

        return $this;
    }

    public function getCompetitiveAnalysis(): ?string
    {
        return $this->competitiveAnalysis;
    }

    public function setCompetitiveAnalysis(?string $competitiveAnalysis): static
    {
        $this->competitiveAnalysis = $competitiveAnalysis;

        return $this;
    }

    public function getSwotAnalysis(): ?string
    {
        return $this->swotAnalysis;
    }

    public function setSwotAnalysis(?string $swotAnalysis): static
    {
        $this->swotAnalysis = $swotAnalysis;

        return $this;
    }

    public function getInvestmentHighlights(): ?string
    {
        return $this->investmentHighlights;
    }

    public function setInvestmentHighlights(?string $investmentHighlights): static
    {
        $this->investmentHighlights = $investmentHighlights;

        return $this;
    }

    public function getRisksAndChallenges(): ?string
    {
        return $this->risksAndChallenges;
    }

    public function setRisksAndChallenges(?string $risksAndChallenges): static
    {
        $this->risksAndChallenges = $risksAndChallenges;

        return $this;
    }

    public function getConclusion(): ?string
    {
        return $this->conclusion;
    }

    public function setConclusion(?string $conclusion): static
    {
        $this->conclusion = $conclusion;

        return $this;
    }

    public function getReportType(): ?string
    {
        return $this->reportType;
    }

    public function setReportType(string $reportType): static
    {
        $this->reportType = $reportType;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getGeneratedBy(): ?string
    {
        return $this->generatedBy;
    }

    public function setGeneratedBy(?string $generatedBy): static
    {
        $this->generatedBy = $generatedBy;

        return $this;
    }
}
