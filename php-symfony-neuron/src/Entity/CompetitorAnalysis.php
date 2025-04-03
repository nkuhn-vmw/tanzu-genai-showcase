<?php

namespace App\Entity;

use App\Repository\CompetitorAnalysisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompetitorAnalysisRepository::class)]
class CompetitorAnalysis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'competitorAnalyses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(length: 255)]
    private ?string $competitorName = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $competitorTickerSymbol = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $overview = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $strengths = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $weaknesses = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $marketShare = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $productComparison = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $financialComparison = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $strategicInitiatives = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getCompetitorName(): ?string
    {
        return $this->competitorName;
    }

    public function setCompetitorName(string $competitorName): static
    {
        $this->competitorName = $competitorName;

        return $this;
    }

    public function getCompetitorTickerSymbol(): ?string
    {
        return $this->competitorTickerSymbol;
    }

    public function setCompetitorTickerSymbol(?string $competitorTickerSymbol): static
    {
        $this->competitorTickerSymbol = $competitorTickerSymbol;

        return $this;
    }

    public function getOverview(): ?string
    {
        return $this->overview;
    }

    public function setOverview(?string $overview): static
    {
        $this->overview = $overview;

        return $this;
    }

    public function getStrengths(): ?string
    {
        return $this->strengths;
    }

    public function setStrengths(?string $strengths): static
    {
        $this->strengths = $strengths;

        return $this;
    }

    public function getWeaknesses(): ?string
    {
        return $this->weaknesses;
    }

    public function setWeaknesses(?string $weaknesses): static
    {
        $this->weaknesses = $weaknesses;

        return $this;
    }

    public function getMarketShare(): ?string
    {
        return $this->marketShare;
    }

    public function setMarketShare(?string $marketShare): static
    {
        $this->marketShare = $marketShare;

        return $this;
    }

    public function getProductComparison(): ?string
    {
        return $this->productComparison;
    }

    public function setProductComparison(?string $productComparison): static
    {
        $this->productComparison = $productComparison;

        return $this;
    }

    public function getFinancialComparison(): ?string
    {
        return $this->financialComparison;
    }

    public function setFinancialComparison(?string $financialComparison): static
    {
        $this->financialComparison = $financialComparison;

        return $this;
    }

    public function getStrategicInitiatives(): ?string
    {
        return $this->strategicInitiatives;
    }

    public function setStrategicInitiatives(?string $strategicInitiatives): static
    {
        $this->strategicInitiatives = $strategicInitiatives;

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
}
